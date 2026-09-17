// The presentation page: the site strip that turns by itself, the brand archive with its featured material, and the
// rhythm chart drawn from the series the module wrote into the page. The rail is the scroll spy of slaed.js, the
// viewer is its lightbox, and the sections rise by the CSS scroll timeline: nothing here is decided twice
(function () {
    'use strict';

    var STEP = 4000;

    function isStill() {
        return window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    }

    // The position read out loud, from the format the module wrote on the gallery
    function getCount(form, at, all) {
        return form.replace('%d', at).replace('%d', all);
    }

    // A figure written the way the module writes one: thousands parted by a space, whatever the locale of the browser
    function getFigure(val) {
        return String(Math.round(val)).replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
    }

    // One gallery: the range and the arrows drive the same position, the strip scrolls by card and the archive shows a
    // feature with four neighbours. The order of the site cards is the module's, so nothing is shuffled here
    function setGallery(root) {
        if (root.getAttribute('data-sl-pres-ready') === '1') return;
        var track = root.querySelector('.sl-pres-gallery-track');
        var range = root.querySelector('[data-sl-pres-range]');
        var out = root.querySelector('output');
        if (!track || !range || !out || !track.children.length) return;
        root.setAttribute('data-sl-pres-ready', '1');
        var cards = Array.prototype.slice.call(track.children);
        var brand = root.getAttribute('data-sl-pres') === 'brand';
        var form = root.getAttribute('data-sl-pres-of') || '%d / %d';
        var at = 0;
        var seen = 1;
        var last = 0;
        var update = function () {
            if (brand) {
                seen = 1;
                last = cards.length - 1;
                for (var i = 0; i < cards.length; i++) {
                    var slot = (i - at + cards.length) % cards.length;
                    cards[i].hidden = slot > 4;
                    if (slot === 0) cards[i].setAttribute('data-sl-pres-feature', '');
                    else cards[i].removeAttribute('data-sl-pres-feature');
                }
                // The feature and its four neighbours stand in slot order, so the grid and the tab order agree
                for (var j = 0; j < cards.length; j++) track.appendChild(cards[(at + j) % cards.length]);
            } else {
                var wide = cards[0].getBoundingClientRect().width;
                var gap = parseFloat(window.getComputedStyle(track).columnGap) || 0;
                seen = Math.max(1, Math.round((track.clientWidth + gap) / (wide + gap)));
                last = Math.max(0, cards.length - seen);
                at = Math.min(last, Math.max(0, Math.round(track.scrollLeft / (wide + gap))));
            }
            range.max = last;
            range.value = at;
            range.setAttribute('aria-valuetext', getCount(form, at + 1, cards.length));
            out.textContent = (at + 1) + (seen > 1 ? '–' + Math.min(cards.length, at + seen) : '') + ' / ' + cards.length;
        };
        var slide = function (next, soft) {
            next = Math.min(last, Math.max(0, next));
            if (brand) {
                at = next;
                update();
                return;
            }
            track.scrollTo({ left: cards[next].offsetLeft, behavior: soft && !isStill() ? 'smooth' : 'instant' });
        };
        root.addEventListener('click', function (event) {
            var step = event.target.closest('[data-sl-pres-step]');
            var open = event.target.closest('[data-sl-pres-open]');
            if (open) {
                var link = open.closest('.sl-pres-tile');
                link = link ? link.querySelector('[data-sl-shot-open]') : null;
                if (link) link.click();
                return;
            }
            if (!step) return;
            var by = parseInt(step.getAttribute('data-sl-pres-step'), 10) || 0;
            slide(by > 0 && at === last ? 0 : by < 0 && at === 0 ? last : at + by * seen, true);
        });
        range.addEventListener('input', function () {
            slide(parseInt(range.value, 10) || 0, false);
        });
        track.addEventListener('keydown', function (event) {
            if (['ArrowLeft', 'ArrowRight', 'Home', 'End'].indexOf(event.key) < 0) return;
            event.preventDefault();
            slide(event.key === 'Home' ? 0 : event.key === 'End' ? last : at + (event.key === 'ArrowRight' ? 1 : -1), true);
            if (brand) track.focus({ preventScroll: true });
        });
        if (!brand) track.addEventListener('scroll', update, { passive: true });
        if (window.ResizeObserver) new ResizeObserver(update).observe(track);
        update();
        if (root.getAttribute('data-sl-pres') === 'sites') setAutoplay(root, function () { slide(at >= last ? 0 : at + 1, true); });
    }

    // The strip turns by itself while it is on screen and nobody points at it or holds focus in it; a hidden tab and a
    // reader who asked for no motion get no step at all
    function setAutoplay(root, step) {
        var held = false;
        var shown = false;
        var timer = 0;
        var arm = function () {
            window.clearInterval(timer);
            timer = shown && !held ? window.setInterval(function () {
                if (document.hidden || isStill()) return;
                step();
            }, STEP) : 0;
        };
        root.addEventListener('pointerenter', function () { held = true; arm(); });
        root.addEventListener('pointerleave', function () { held = false; arm(); });
        root.addEventListener('focusin', function () { held = true; arm(); });
        root.addEventListener('focusout', function (event) {
            if (root.contains(event.relatedTarget)) return;
            held = false;
            arm();
        });
        if (!window.IntersectionObserver) {
            shown = true;
            arm();
            return;
        }
        new IntersectionObserver(function (rows) {
            shown = rows[0].isIntersecting;
            arm();
        }, { threshold: 0.25 }).observe(root);
    }

    // A token resolved to a colour the canvas can take: the raw value of light-dark() is not one, the computed colour of an element painted with it is
    function getTone(host, name) {
        var probe = document.createElement('span');
        probe.hidden = true;
        probe.style.color = 'var(' + name + ')';
        host.appendChild(probe);
        var tone = window.getComputedStyle(probe).color;
        host.removeChild(probe);
        return tone || '#888888';
    }

    // The rhythm chart: the visits by hour, day of the week or day of the month, and under them the share the page cache
    // answered and the rest that reached the database, all three written by the module. The headline follows the period,
    // and the change is shown only where a like period precedes it. The spark of the runtime section draws the visits alone
    function setChart(root) {
        if (root.getAttribute('data-sl-pres-ready') === '1') return;
        var face = root.querySelector('canvas');
        var tip = root.querySelector('output');
        var mini = root.getAttribute('data-sl-pres') === 'spark';
        var sets = {};
        try { sets = JSON.parse(root.getAttribute('data-sl-pres-series') || '{}'); } catch (err) { sets = {}; }
        if (!face || (!tip && !mini) || !sets.day) return;
        root.setAttribute('data-sl-pres-ready', '1');
        var plot = face.parentElement;
        var head = root.querySelector('.sl-pres-chart-meta strong');
        var diff = root.querySelector('.sl-pres-chart-meta b');
        var names = Array.prototype.map.call(root.querySelectorAll('.sl-pres-legend span'), function (one) { return one.textContent.trim(); });
        var dayhead = head ? head.textContent : '';
        var daydiff = diff ? diff.textContent : '';
        var span = 'day';
        var kind = 'area';
        var hover = -1;
        var geo = { left: 0, right: 0, count: 0 };
        var getRows = function () {
            var set = sets[span] || {};
            var rows = [set.visits || []];
            if (!mini && set.cache) rows.push(set.cache);
            if (!mini && set.db) rows.push(set.db);
            return rows;
        };
        var getLabel = function (i) {
            var labs = (sets[span] || {}).labels || [];
            var lab = String(labs[i] === undefined ? '' : labs[i]);
            return span === 'day' ? (lab.length < 2 ? '0' + lab : lab) + ':00' : lab.slice(0, 5);
        };
        var getSum = function (list) {
            var sum = 0;
            for (var i = 0; i < list.length; i++) sum += list[i];
            return sum;
        };
        var setHead = function () {
            if (!head) return;
            var vis = (sets[span] || {}).visits || [];
            head.textContent = span === 'day' ? dayhead : getFigure(getSum(vis));
            if (!diff) return;
            var all = (sets.month || {}).visits || [];
            var prev = span === 'week' && all.length >= 14 ? getSum(all.slice(-14, -7)) : 0;
            diff.hidden = span !== 'day' && prev < 1;
            if (span === 'day') diff.textContent = daydiff;
            if (span === 'week' && prev > 0) {
                var pct = Math.round((getSum(all.slice(-7)) - prev) * 100 / prev);
                diff.textContent = (pct >= 0 ? '+' : '') + pct + '%';
            }
        };
        var draw = function () {
            var rect = plot.getBoundingClientRect();
            if (!rect.width || !rect.height) return;
            var dpr = Math.min(window.devicePixelRatio || 1, 2);
            face.width = Math.round(rect.width * dpr);
            face.height = Math.round(rect.height * dpr);
            var pen = face.getContext('2d');
            pen.setTransform(dpr, 0, 0, dpr, 0, 0);
            var rows = getRows();
            var top = 1;
            for (var r = 0; r < rows.length; r++) for (var c = 0; c < rows[r].length; c++) top = Math.max(top, rows[r][c]);
            var tones = [getTone(root, '--sl-primary'), getTone(root, '--sl-accent'), getTone(root, '--sl-pres-good-text')];
            var mute = getTone(root, '--sl-text-muted');
            var line = getTone(root, '--sl-border');
            var style = window.getComputedStyle(root);
            pen.font = style.getPropertyValue('--sl-font-micro').trim() + ' ' + style.fontFamily;
            pen.textBaseline = 'middle';
            var left = mini ? 0 : Math.ceil(pen.measureText(getFigure(top)).width) + 8;
            var right = rect.width - (mini ? 0 : 10);
            var high = mini ? 4 : 12;
            var low = rect.height - (mini ? 4 : 30);
            var count = rows[0].length;
            geo = { left: left, right: right, count: count };
            var getX = function (i) { return left + (count > 1 ? i / (count - 1) : 0) * (right - left); };
            var getY = function (val) { return low - val / top * (low - high); };
            for (var i = 0; !mini && i <= 4; i++) {
                var y = high + (low - high) * i / 4;
                pen.strokeStyle = line;
                pen.setLineDash([3, 5]);
                pen.beginPath();
                pen.moveTo(left, y);
                pen.lineTo(right, y);
                pen.stroke();
                pen.fillStyle = mute;
                pen.textAlign = 'left';
                pen.fillText(getFigure(top * (4 - i) / 4), 0, y);
            }
            pen.setLineDash([]);
            for (var s = 0; s < rows.length; s++) {
                var data = rows[s];
                pen.strokeStyle = tones[s];
                pen.fillStyle = tones[s];
                pen.lineWidth = s ? 1.5 : 2.5;
                if (kind === 'bar') {
                    var wide = (right - left) / Math.max(1, count);
                    pen.globalAlpha = s ? 0.6 : 0.9;
                    for (var b = 0; b < count; b++) pen.fillRect(getX(b) + (s - 1) * wide * 0.22, getY(data[b]), Math.max(1, wide * 0.2), low - getY(data[b]));
                    pen.globalAlpha = 1;
                    continue;
                }
                pen.beginPath();
                pen.moveTo(getX(0), getY(data[0] || 0));
                for (var p = 1; p < count; p++) {
                    var mid = (getX(p - 1) + getX(p)) / 2;
                    pen.bezierCurveTo(mid, getY(data[p - 1] || 0), mid, getY(data[p] || 0), getX(p), getY(data[p] || 0));
                }
                pen.stroke();
                if (s) continue;
                pen.lineTo(right, low);
                pen.lineTo(left, low);
                pen.closePath();
                var fade = pen.createLinearGradient(0, high, 0, low);
                fade.addColorStop(0, tones[0]);
                fade.addColorStop(1, 'transparent');
                pen.fillStyle = fade;
                pen.globalAlpha = 0.17;
                pen.fill();
                pen.globalAlpha = 1;
            }
            pen.fillStyle = mute;
            for (var t = 0; !mini && t < 5; t++) {
                var idx = Math.round((count - 1) * t / 4);
                pen.textAlign = t === 0 ? 'left' : t === 4 ? 'right' : 'center';
                pen.fillText(getLabel(idx), getX(idx), rect.height - 10);
            }
            if (hover < 0) return;
            var here = Math.min(count - 1, hover);
            pen.strokeStyle = mute;
            pen.setLineDash([3, 3]);
            pen.beginPath();
            pen.moveTo(getX(here), high);
            pen.lineTo(getX(here), low);
            pen.stroke();
            pen.setLineDash([]);
            for (var d = 0; d < rows.length; d++) {
                pen.fillStyle = tones[d];
                pen.beginPath();
                pen.arc(getX(here), getY(rows[d][here] || 0), 4, 0, Math.PI * 2);
                pen.fill();
            }
        };
        face.addEventListener('pointermove', function (event) {
            if (!tip) return;
            var rect = face.getBoundingClientRect();
            var rows = getRows();
            if (geo.count < 1 || geo.right <= geo.left) return;
            hover = Math.max(0, Math.min(geo.count - 1, Math.round((event.clientX - rect.left - geo.left) / (geo.right - geo.left) * (geo.count - 1))));
            var text = getLabel(hover);
            for (var i = 0; i < rows.length; i++) text += ' · ' + getFigure(rows[i][hover] || 0) + ' ' + (names[i] || '');
            tip.textContent = text;
            tip.hidden = false;
            draw();
        });
        face.addEventListener('pointerleave', function () {
            hover = -1;
            if (tip) tip.hidden = true;
            draw();
        });
        root.addEventListener('click', function (event) {
            var when = event.target.closest('[data-sl-pres-period]');
            var view = event.target.closest('[data-sl-pres-view]');
            var pick = when || view;
            if (!pick) return;
            if (when) span = when.getAttribute('data-sl-pres-period');
            else kind = view.getAttribute('data-sl-pres-view');
            var kin = pick.parentElement.querySelectorAll('button');
            for (var i = 0; i < kin.length; i++) kin[i].setAttribute('aria-pressed', kin[i] === pick ? 'true' : 'false');
            setHead();
            draw();
        });
        if (window.ResizeObserver) new ResizeObserver(draw).observe(plot);
        var dark = window.matchMedia('(prefers-color-scheme: dark)');
        if (dark.addEventListener) dark.addEventListener('change', draw);
        setHead();
        draw();
    }

    // The pulse strip of the control window: a band of figures drifting towards fresh targets the way a load monitor
    // breathes. Nothing here is measured, so the strip says so by never standing still; a reader who asked for no motion
    // gets one still band
    function setPulse(root) {
        if (root.getAttribute('data-sl-pres-ready') === '1') return;
        var face = root.querySelector('canvas');
        if (!face) return;
        root.setAttribute('data-sl-pres-ready', '1');
        var count = 44;
        var aim = [];
        var cur = [];
        for (var i = 0; i < count; i++) {
            aim.push(24 + Math.random() * 54);
            cur.push(aim[i]);
        }
        var last = 0;
        var tone = '';
        var paint = function (now) {
            var rect = root.getBoundingClientRect();
            if (!rect.width || !rect.height) {
                window.requestAnimationFrame(paint);
                return;
            }
            var dpr = Math.min(window.devicePixelRatio || 1, 2);
            var wide = Math.round(rect.width * dpr);
            var high = Math.round(rect.height * dpr);
            if (face.width !== wide || face.height !== high) {
                face.width = wide;
                face.height = high;
            }
            if (!tone || now - last > 900) tone = getTone(root, '--sl-primary');
            if (now - last > 900) {
                aim.push(24 + Math.random() * 54);
                aim.shift();
                last = now;
            }
            for (var k = 0; k < count; k++) cur[k] += (aim[k] - cur[k]) * 0.1;
            var pen = face.getContext('2d');
            pen.setTransform(dpr, 0, 0, dpr, 0, 0);
            pen.clearRect(0, 0, rect.width, rect.height);
            var getX = function (at) { return at / (count - 1) * rect.width; };
            var getY = function (val) { return rect.height - val / 100 * (rect.height - 4) - 2; };
            pen.beginPath();
            pen.moveTo(getX(0), getY(cur[0]));
            for (var p = 1; p < count; p++) {
                var mid = (getX(p - 1) + getX(p)) / 2;
                pen.bezierCurveTo(mid, getY(cur[p - 1]), mid, getY(cur[p]), getX(p), getY(cur[p]));
            }
            pen.strokeStyle = tone;
            pen.lineWidth = 1.75;
            pen.lineJoin = 'round';
            pen.lineCap = 'round';
            pen.stroke();
            pen.lineTo(getX(count - 1), rect.height);
            pen.lineTo(getX(0), rect.height);
            pen.closePath();
            var fade = pen.createLinearGradient(0, 0, 0, rect.height);
            fade.addColorStop(0, tone);
            fade.addColorStop(1, 'transparent');
            pen.fillStyle = fade;
            pen.globalAlpha = 0.22;
            pen.fill();
            pen.globalAlpha = 1;
            if (!isStill()) window.requestAnimationFrame(paint);
        };
        window.requestAnimationFrame(paint);
    }

    // A figure that breathes: the value the module wrote is the centre, and every few seconds it moves within an eighth of itself
    function setLive(node) {
        var base = parseInt(node.textContent, 10);
        if (isNaN(base) || isStill()) return;
        window.setInterval(function () {
            if (document.hidden) return;
            node.textContent = getFigure(base + Math.round((Math.random() - 0.5) * base / 4));
        }, 2700);
    }

    // The control window: every section of its menu carries its own trace, the log and the path of the bar follow the
    // pressed one, and the window walks its sections by itself until somebody presses one
    function setCore(root) {
        if (root.getAttribute('data-sl-pres-ready') === '1') return;
        var keys = root.querySelectorAll('.sl-pres-core-menu > button');
        var log = root.querySelector('.sl-pres-log');
        var path = root.querySelector('.sl-pres-core-bar > span:last-child');
        if (!keys.length || !log) return;
        root.setAttribute('data-sl-pres-ready', '1');
        var at = 0;
        var held = false;
        var show = function (next) {
            at = next;
            var rows = [];
            try { rows = JSON.parse(keys[at].getAttribute('data-sl-pres-trace') || '[]'); } catch (err) { rows = []; }
            for (var i = 0; i < keys.length; i++) keys[i].setAttribute('aria-pressed', i === at ? 'true' : 'false');
            if (path) path.textContent = keys[at].getAttribute('data-sl-pres-path') || '';
            log.textContent = '';
            for (var r = 0; r < rows.length; r++) {
                var row = document.createElement('div');
                var when = document.createElement('time');
                var name = document.createElement('span');
                var val = document.createElement('em');
                when.textContent = rows[r].time || '';
                name.textContent = rows[r].name || '';
                val.textContent = rows[r].value || '';
                val.setAttribute('data-sl-tone', 'success');
                row.appendChild(when);
                row.appendChild(name);
                row.appendChild(val);
                log.appendChild(row);
            }
        };
        root.addEventListener('click', function (event) {
            var key = event.target.closest('.sl-pres-core-menu > button');
            if (!key) return;
            held = true;
            show(Array.prototype.indexOf.call(keys, key));
        });
        setAutoplay(root, function () {
            if (!held) show((at + 1) % keys.length);
        });
        var live = root.querySelectorAll('[data-sl-pres-live]');
        for (var l = 0; l < live.length; l++) setLive(live[l]);
    }

    // The build presets of the module map: a preset lights its set on the nodes and on the wires standing in the same
    // order, and a preset reads as pressed while the map shows exactly its set. The nodes stay links; nothing is saved
    function setBuild(root) {
        if (root.getAttribute('data-sl-pres-ready') === '1') return;
        root.setAttribute('data-sl-pres-ready', '1');
        var mods = root.querySelectorAll('[data-sl-pres-mod]');
        var wires = root.querySelectorAll('.sl-pres-map .sl-pres-wires path');
        var btns = root.querySelectorAll('[data-sl-pres-build]');
        var getSet = function (btn) { return btn.getAttribute('data-sl-pres-build').split(','); };
        var press = function () {
            for (var b = 0; b < btns.length; b++) {
                var set = getSet(btns[b]);
                var same = true;
                for (var m = 0; m < mods.length; m++) if (mods[m].classList.contains('sl-is-on') !== (set.indexOf(mods[m].getAttribute('data-sl-pres-mod')) >= 0)) same = false;
                btns[b].setAttribute('aria-pressed', same ? 'true' : 'false');
            }
        };
        root.addEventListener('click', function (event) {
            var pick = event.target.closest('[data-sl-pres-build]');
            if (!pick) return;
            var set = getSet(pick);
            for (var m = 0; m < mods.length; m++) {
                var on = set.indexOf(mods[m].getAttribute('data-sl-pres-mod')) >= 0;
                mods[m].classList.toggle('sl-is-on', on);
                mods[m].classList.toggle('sl-is-off', !on);
                if (wires[m]) wires[m].classList.toggle('sl-is-on', on);
            }
            press();
        });
        press();
    }

    // The request guard: every half beat a traveller leaves the pool, rides a lane to the gate, and the gate, patrolling
    // its track, slides to meet it - a bad request drops into quarantine, a good one flies to its window of the house and
    // lights it. The plugin clones the cards the template rendered and writes positions as --sl-d-*; every phase is a share
    // of STEP, the beat the CSS transitions of the traveller carry, and the log grows by a clone of its own first row
    function setGuard(root) {
        if (root.getAttribute('data-sl-pres-ready') === '1' || isStill()) return;
        var scene = root.querySelector('.sl-pres-guard-scene');
        var gate = root.querySelector('.sl-pres-gate');
        var pool = root.querySelectorAll('.sl-pres-travelers > .sl-pres-traveler');
        var lanes = root.querySelectorAll('.sl-pres-lanes > div');
        var box = root.querySelector('.sl-pres-quarantine');
        var log = root.querySelector('.sl-pres-log');
        if (!scene || !gate || !pool.length || !lanes.length || !box) return;
        root.setAttribute('data-sl-pres-ready', '1');
        var word = gate.querySelector('small');
        var count = box.querySelector('b');
        var scan = word ? word.textContent : '';
        var at = 0;
        var aim = -1;
        var top = 0;
        var dir = 1;
        var last = 0;
        var shown = false;
        var rel = function (el) {
            var s = scene.getBoundingClientRect();
            var r = el.getBoundingClientRect();
            return { left: r.left - s.left, top: r.top - s.top, width: r.width, height: r.height };
        };
        var setState = function (state, text) {
            if (state) gate.setAttribute('data-sl-pres-state', state);
            else gate.removeAttribute('data-sl-pres-state');
            if (word) word.textContent = text || scan;
        };
        var release = function () {
            window.setTimeout(function () { aim = -1; setState('', ''); }, STEP * 0.03);
        };
        var patrol = function (now) {
            if (!shown) return;
            var dt = Math.min(35, now - last || 16);
            last = now;
            var g = rel(gate);
            var first = rel(lanes[0]);
            var end = rel(lanes[lanes.length - 1]);
            var low = first.top;
            var high = end.top + end.height - g.height;
            if (aim >= 0) top += (Math.max(low, Math.min(high, aim - g.height / 2)) - top) * Math.min(1, dt * 0.014);
            else {
                top += dir * dt * 0.075;
                if (top >= high) { top = high; dir = -1; }
                if (top <= low) { top = low; dir = 1; }
            }
            scene.style.setProperty('--sl-d-gate-y', top.toFixed(1) + 'px');
            window.requestAnimationFrame(patrol);
        };
        var note = function (card) {
            if (!log || !log.firstElementChild) return;
            var row = log.firstElementChild.cloneNode(true);
            var em = row.querySelector('em');
            row.querySelector('span').textContent = card.querySelector('b').textContent + ' · ' + card.querySelector('small').textContent;
            if (em) {
                em.textContent = card.getAttribute('data-sl-pres-result');
                em.setAttribute('data-sl-tone', card.getAttribute('data-sl-pres-verdict'));
            }
            log.insertBefore(row, log.firstElementChild);
            while (log.children.length > 6) log.removeChild(log.lastElementChild);
        };
        var block = function (card) {
            setState('block', gate.getAttribute('data-sl-pres-block'));
            card.classList.add('sl-is-caught');
            var q = rel(box);
            card.style.setProperty('--sl-d-x', Math.round(q.left + 16 + Math.random() * Math.max(0, q.width - 96)) + 'px');
            card.style.setProperty('--sl-d-y', Math.round(q.top + q.height * 0.4) + 'px');
            window.setTimeout(function () {
                if (count) count.textContent = String((parseInt(count.textContent, 10) || 0) + 1);
                note(card);
                card.remove();
                release();
            }, STEP * 0.17);
        };
        var pass = function (card) {
            setState('pass', gate.getAttribute('data-sl-pres-pass'));
            card.classList.add('sl-is-passed');
            var zone = card.getAttribute('data-sl-pres-zone');
            var home = zone === 'door' ? root.querySelector('.sl-pres-door') : root.querySelector('.sl-pres-pane:nth-child(' + zone + ')');
            var h = rel(home || root.querySelector('.sl-pres-house'));
            var c = rel(card);
            card.style.setProperty('--sl-d-x', Math.round(h.left + h.width / 2 - c.width / 2) + 'px');
            card.style.setProperty('--sl-d-y', Math.round(h.top + h.height / 2 - c.height / 2) + 'px');
            window.setTimeout(function () {
                if (home) {
                    home.classList.add('sl-is-lit');
                    window.setTimeout(function () { home.classList.remove('sl-is-lit'); }, STEP * 0.2);
                }
                note(card);
                release();
            }, STEP * 0.25);
            window.setTimeout(function () { card.remove(); }, STEP * 0.35);
        };
        var fly = function () {
            var card = pool[at % pool.length].cloneNode(true);
            var lane = rel(lanes[at % lanes.length]);
            var y = lane.top + lane.height / 2;
            at++;
            card.style.setProperty('--sl-d-x', '-' + Math.round(lane.left) + 'px');
            card.style.setProperty('--sl-d-y', Math.round(y) + 'px');
            scene.appendChild(card);
            var c = rel(card);
            var g = rel(gate);
            card.style.setProperty('--sl-d-y', Math.round(y - c.height / 2) + 'px');
            card.style.setProperty('--sl-d-x', Math.round(g.left - c.width - 8) + 'px');
            window.setTimeout(function () { aim = y; setState('check', ''); }, STEP * 0.18);
            window.setTimeout(function () {
                if (card.hasAttribute('data-sl-pres-bad')) block(card);
                else pass(card);
            }, STEP * 0.43);
        };
        var wake = function (on) {
            shown = on;
            if (!on) return;
            if (!scene.hasAttribute('data-sl-pres-live')) {
                top = rel(gate).top;
                scene.setAttribute('data-sl-pres-live', '');
            }
            last = window.performance.now();
            window.requestAnimationFrame(patrol);
        };
        if (window.IntersectionObserver) new IntersectionObserver(function (rows) { wake(rows[0].isIntersecting); }, { threshold: 0.25 }).observe(scene);
        else wake(true);
        window.setInterval(function () { if (shown && !document.hidden) fly(); }, STEP / 2);
    }

    // The PDO stage: every beat the next case of the pool is written onto the query line, the console and the trace, the
    // packet walks the five nodes at a step a share of the beat, and at the end the counters of the console grow by the
    // case, the row bars take fresh shares and a write flashes the cache epoch. Cloning the first param chip is the only
    // markup ever made here; everything else is text and attributes on what the template rendered
    function setPdo(root) {
        if (root.getAttribute('data-sl-pres-ready') === '1' || isStill()) return;
        var stage = root.querySelector('.sl-pres-pdo-stage');
        var cases = root.querySelectorAll('[data-sl-pres-cases] > div');
        var nodes = root.querySelectorAll('.sl-pres-pdo-stage > .sl-pres-node');
        var packet = root.querySelector('.sl-pres-packet');
        if (!stage || !cases.length || !nodes.length || !packet) return;
        root.setAttribute('data-sl-pres-ready', '1');
        var query = root.querySelector('.sl-pres-query');
        var verb = query ? query.querySelector('span') : null;
        var code = query ? query.querySelector('code') : null;
        var params = query ? query.querySelector('.sl-pres-params') : null;
        var state = root.querySelector('.sl-pres-pdo-head > em');
        var mode = root.querySelector('.sl-pres-console-head > span');
        var result = root.querySelector('.sl-pres-result b');
        var elapsed = root.querySelector('.sl-pres-result > em');
        var pres = root.querySelectorAll('.sl-pres-code');
        var trace = root.querySelectorAll('.sl-pres-trace > div');
        var stats = root.querySelectorAll('.sl-pres-console-grid .sl-pres-stat > b');
        var bars = root.querySelectorAll('.sl-pres-bars > i');
        var epoch = root.querySelector('.sl-pres-epoch');
        var at = 0;
        var step = 0;
        var timer = 0;
        var count = stats[0] ? parseInt(stats[0].firstChild.nodeValue, 10) || 0 : 0;
        var total = stats[1] ? parseFloat(stats[1].firstChild.nodeValue) || 0 : 0;
        var setTone = function (el, tone) {
            if (!el) return;
            if (tone) el.setAttribute('data-sl-tone', tone);
            else el.removeAttribute('data-sl-tone');
        };
        var setBars = function () {
            for (var b = 0; b < bars.length; b++) bars[b].style.setProperty('--sl-d-part', Math.round(25 + Math.random() * 68) + '%');
        };
        var paint = function (c) {
            var write = c.hasAttribute('data-sl-pres-write');
            var prepared = c.hasAttribute('data-sl-pres-prepared');
            if (verb) verb.textContent = c.getAttribute('data-sl-pres-verb');
            if (code) code.textContent = c.getAttribute('data-sl-pres-query');
            if (params && params.firstElementChild) {
                var list = c.getAttribute('data-sl-pres-params');
                list = list ? list.split('|') : [];
                for (var i = params.children.length; i < list.length; i++) params.appendChild(params.firstElementChild.cloneNode(true));
                for (var j = 0; j < params.children.length; j++) {
                    params.children[j].hidden = j >= list.length;
                    if (j < list.length) params.children[j].textContent = list[j];
                }
            }
            if (state) state.textContent = c.getAttribute('data-sl-pres-state');
            setTone(state, write ? 'warning' : '');
            setTone(query, write ? 'warning' : '');
            setTone(packet, write ? 'warning' : '');
            if (mode) mode.textContent = c.getAttribute('data-sl-pres-mode');
            if (result) result.textContent = c.getAttribute('data-sl-pres-result');
            if (elapsed) elapsed.textContent = c.getAttribute('data-sl-pres-elapsed') + ' s';
            for (var p = 0; p < pres.length; p++) pres[p].hidden = (p === 0) !== prepared;
            if (trace.length < 3) return;
            trace[0].querySelector('b').textContent = prepared ? 'pdo.prepare' : 'pdo.query';
            trace[0].querySelector('em').textContent = c.getAttribute('data-sl-pres-mode');
            trace[1].querySelector('em').textContent = c.getAttribute('data-sl-pres-state');
            setTone(trace[1].querySelector('em'), write ? 'warning' : 'success');
            trace[2].querySelector('em').textContent = c.getAttribute('data-sl-pres-result');
        };
        var run = function () {
            window.clearInterval(timer);
            var c = cases[at++ % cases.length];
            paint(c);
            setBars();
            stage.setAttribute('data-sl-pres-live', '');
            for (var n = 0; n < nodes.length; n++) nodes[n].classList.toggle('sl-is-on', n === 0);
            packet.style.setProperty('--sl-d-x', '10%');
            step = 0;
            timer = window.setInterval(function () {
                nodes[step].classList.remove('sl-is-on');
                step++;
                if (step >= nodes.length) {
                    window.clearInterval(timer);
                    count++;
                    total += parseFloat(c.getAttribute('data-sl-pres-elapsed')) || 0;
                    if (stats[0]) stats[0].firstChild.nodeValue = String(count);
                    if (stats[1]) stats[1].firstChild.nodeValue = total.toFixed(4) + ' ';
                    if (epoch && c.hasAttribute('data-sl-pres-write')) {
                        epoch.classList.add('sl-is-on');
                        window.setTimeout(function () { epoch.classList.remove('sl-is-on'); }, STEP * 0.42);
                    }
                    return;
                }
                nodes[step].classList.add('sl-is-on');
                packet.style.setProperty('--sl-d-x', (10 + step * 20) + '%');
            }, STEP * 0.15);
        };
        setAutoplay(stage, run);
    }

    // The event strip: the last card comes to the front with the time of now, so the five on screen keep changing
    function setEvents(root) {
        if (root.getAttribute('data-sl-pres-ready') === '1' || isStill() || root.children.length < 2) return;
        root.setAttribute('data-sl-pres-ready', '1');
        setAutoplay(root, function () {
            var last = root.lastElementChild;
            var when = last.querySelector('time');
            var now = new Date();
            if (when) when.textContent = [now.getHours(), now.getMinutes(), now.getSeconds()].map(function (n) { return (n < 10 ? '0' : '') + n; }).join(':');
            root.insertBefore(last, root.firstElementChild);
        });
    }

    // The commit board: the focus walks the rows one beat at a time and follows the pointer, and the focus panel takes
    // the row's sha, date, title, text and chips - the chips as clones of the row's own, the last chip (the author) kept
    function setDev(root) {
        if (root.getAttribute('data-sl-pres-ready') === '1') return;
        var rows = root.querySelectorAll('.sl-pres-commit');
        var focus = root.querySelector('.sl-pres-focus');
        if (rows.length < 2 || !focus) return;
        root.setAttribute('data-sl-pres-ready', '1');
        var state = focus.querySelector('.sl-pres-panel-head > b');
        var mono = focus.querySelector('.sl-pres-mono');
        var title = focus.querySelector('h3');
        var text = focus.querySelector('p');
        var tags = focus.querySelector('.sl-pres-tags');
        var head = state ? state.textContent : '';
        var at = 0;
        var show = function (next) {
            at = next;
            for (var i = 0; i < rows.length; i++) rows[i].classList.toggle('sl-is-on', i === at);
            var row = rows[at];
            var sha = row.querySelector('.sl-pres-sha');
            var when = row.querySelector('time');
            var note = row.querySelector('small');
            if (state) state.textContent = at === 0 ? head : root.getAttribute('data-sl-pres-recent');
            if (mono && sha && when) mono.textContent = sha.textContent + ' · ' + when.textContent;
            if (title) title.textContent = row.querySelector('b').textContent;
            if (text) {
                text.textContent = row.getAttribute('data-sl-pres-text') || '';
                text.hidden = !text.textContent;
            }
            if (!tags) return;
            while (tags.firstChild) tags.removeChild(tags.firstChild);
            var chips = row.querySelectorAll('.sl-pres-tags > span');
            for (var c = 0; c < chips.length; c++) tags.appendChild(chips[c].cloneNode(true));
            if (note) {
                var last = chips.length ? chips[0].cloneNode(true) : document.createElement('span');
                last.textContent = note.textContent;
                tags.appendChild(last);
            }
        };
        for (var r = 0; r < rows.length; r++) rows[r].addEventListener('pointerenter', function (event) { show(Array.prototype.indexOf.call(rows, event.currentTarget)); });
        setAutoplay(root, function () { show((at + 1) % rows.length); });
    }

    // The poll heartbeat of the statistics board: the word the template wrote, then one to three dots, round and round
    function setSync(el) {
        if (el.getAttribute('data-sl-pres-ready') === '1' || isStill()) return;
        el.setAttribute('data-sl-pres-ready', '1');
        var base = el.textContent;
        var n = 0;
        window.setInterval(function () {
            if (document.hidden) return;
            n = (n + 1) % 4;
            el.textContent = n ? base + ' ' + '···'.slice(0, n) : base;
        }, STEP * 0.3);
    }

    // The request path: every beat the flow takes the next scenario of the pool - its mode on the scene, its badge, route,
    // core lines, gate and parser words, the four states of the side grid and the case of the decision tree - and walks
    // the packet's nodes one by one; a miss ends with the gate storing the page, which the badge says for a moment
    function setFlow(root) {
        if (root.getAttribute('data-sl-pres-ready') === '1' || isStill()) return;
        var scene = root.querySelector('.sl-pres-flow');
        var pool = root.querySelectorAll('[data-sl-pres-flows] > div');
        var nodes = root.querySelectorAll('.sl-pres-flow > .sl-pres-node');
        if (!scene || pool.length < 2 || nodes.length < 7) return;
        root.setAttribute('data-sl-pres-ready', '1');
        var badge = root.querySelector('.sl-pres-flow-top .sl-pres-pill');
        var route = root.querySelector('.sl-pres-flow-top .sl-pres-mono');
        var sub = root.querySelector('.sl-pres-flow-core small');
        var state = root.querySelector('.sl-pres-flow-core .sl-pres-pill');
        var gate = nodes[2].querySelectorAll('small > span');
        var mod = nodes[4].querySelectorAll('small > span');
        var cases = root.querySelectorAll('.sl-pres-case');
        var stats = root.querySelectorAll('.sl-pres-states > .sl-pres-stat:not(.sl-pres-epoch-row)');
        var at = 0;
        var timer = 0;
        var setTone = function (el, tone) {
            if (!el) return;
            if (tone) el.setAttribute('data-sl-tone', tone);
            else el.removeAttribute('data-sl-tone');
        };
        var setBadge = function (text, tone) {
            if (!badge) return;
            badge.textContent = text;
            setTone(badge, tone);
        };
        var paint = function (c) {
            var mode = c.getAttribute('data-sl-pres-mode');
            scene.setAttribute('data-sl-mode', mode);
            setBadge(c.getAttribute('data-sl-pres-badge'), c.getAttribute('data-sl-pres-btone'));
            if (route) route.textContent = c.getAttribute('data-sl-pres-route');
            if (sub) sub.textContent = c.getAttribute('data-sl-pres-sub');
            if (state) {
                state.textContent = c.getAttribute('data-sl-pres-state');
                setTone(state, c.getAttribute('data-sl-pres-btone'));
            }
            if (gate.length > 1) {
                gate[0].textContent = c.getAttribute('data-sl-pres-gate-a');
                gate[1].textContent = c.getAttribute('data-sl-pres-gate-b');
            }
            if (mod.length > 1) mod[1].textContent = c.getAttribute('data-sl-pres-mod-b');
            var pick = mode === 'off' ? 'bypass' : mode;
            for (var k = 0; k < cases.length; k++) cases[k].classList.toggle('sl-is-on', cases[k].querySelector('strong').textContent.toLowerCase() === pick);
            var texts = c.getAttribute('data-sl-pres-states').split('|');
            var tones = c.getAttribute('data-sl-pres-tones').split('|');
            for (var s = 0; s < stats.length && s < texts.length; s++) {
                var b = stats[s].querySelector('b');
                if (b) b.textContent = texts[s];
                setTone(stats[s], tones[s] || '');
            }
        };
        var run = function () {
            window.clearInterval(timer);
            var c = pool[at++ % pool.length];
            var seq = c.getAttribute('data-sl-pres-seq').split(',');
            var i = 0;
            paint(c);
            var light = function () {
                for (var n = 0; n < nodes.length; n++) nodes[n].classList.toggle('sl-is-on', String(n + 1) === seq[i] || (i === seq.length - 1 && c.getAttribute('data-sl-pres-mode') === 'miss' && n === 2));
                if (i === seq.length - 1 && c.getAttribute('data-sl-pres-mode') === 'miss') setBadge(root.getAttribute('data-sl-pres-store'), 'success');
            };
            light();
            timer = window.setInterval(function () {
                i++;
                if (i >= seq.length) {
                    window.clearInterval(timer);
                    return;
                }
                light();
            }, STEP * 0.12);
        };
        setAutoplay(scene, run);
    }

    function setPresentation() {
        var root = document.querySelector('.sl-pres');
        if (root && root.getAttribute('data-sl-pres-light') !== '1') {
            root.setAttribute('data-sl-pres-light', '1');
            var frame = 0;
            var point = { left: 0, top: 0 };
            document.addEventListener('pointermove', function (event) {
                if (event.pointerType === 'touch' || isStill()) return;
                point.left = event.clientX;
                point.top = event.clientY;
                if (frame) return;
                frame = window.requestAnimationFrame(function () {
                    root.style.setProperty('--sl-d-pointer-x', point.left + 'px');
                    root.style.setProperty('--sl-d-pointer-y', point.top + 'px');
                    root.setAttribute('data-sl-pres-pointer', '');
                    frame = 0;
                });
            }, { passive: true });
            var clear = function () {
                window.cancelAnimationFrame(frame);
                frame = 0;
                root.removeAttribute('data-sl-pres-pointer');
            };
            document.documentElement.addEventListener('pointerleave', clear);
            document.addEventListener('pointercancel', clear);
            window.addEventListener('blur', clear);
        }
        var list = document.querySelectorAll('[data-sl-pres]');
        for (var i = 0; i < list.length; i++) {
            var kind = list[i].getAttribute('data-sl-pres');
            if (kind === 'pulse') setPulse(list[i]);
            else if (kind === 'core') setCore(list[i]);
            else if (kind === 'chart' || kind === 'spark') setChart(list[i]);
            else if (kind === 'build') setBuild(list[i]);
            else if (kind === 'guard') setGuard(list[i]);
            else if (kind === 'pdo') setPdo(list[i]);
            else if (kind === 'events') setEvents(list[i]);
            else if (kind === 'dev') setDev(list[i]);
            else if (kind === 'sync') setSync(list[i]);
            else if (kind === 'flow') setFlow(list[i]);
            else setGallery(list[i]);
        }
        var tabs = document.querySelectorAll('.sl-pres-tabs');
        for (var j = 0; j < tabs.length; j++) {
            if (tabs[j].getAttribute('data-sl-pres-ready') === '1') continue;
            tabs[j].setAttribute('data-sl-pres-ready', '1');
            tabs[j].setAttribute('role', 'tablist');
            var btns = tabs[j].querySelectorAll('button');
            var panes = tabs[j].closest('.sl-pres-devtools').querySelectorAll('.sl-pres-tab-pane');
            for (var k = 0; k < btns.length; k++) {
                var on = btns[k].classList.contains('sl-is-on');
                btns[k].id = 'pres-tab-' + j + '-' + k;
                btns[k].setAttribute('role', 'tab');
                btns[k].setAttribute('aria-selected', String(on));
                btns[k].setAttribute('aria-controls', 'pres-pane-' + j + '-' + k);
                btns[k].tabIndex = on ? 0 : -1;
                panes[k].id = 'pres-pane-' + j + '-' + k;
                panes[k].setAttribute('role', 'tabpanel');
                panes[k].setAttribute('aria-labelledby', btns[k].id);
                panes[k].hidden = !on;
            }
            tabs[j].addEventListener('click', function (event) {
                var pick = event.target.closest('button');
                if (!pick) return;
                var btns = event.currentTarget.querySelectorAll('button');
                var panes = event.currentTarget.closest('.sl-pres-devtools').querySelectorAll('.sl-pres-tab-pane');
                for (var i = 0; i < btns.length; i++) {
                    var on = btns[i] === pick;
                    btns[i].classList.toggle('sl-is-on', on);
                    btns[i].setAttribute('aria-selected', String(on));
                    btns[i].tabIndex = on ? 0 : -1;
                    panes[i].classList.toggle('sl-is-on', on);
                    panes[i].hidden = !on;
                }
            });
            tabs[j].addEventListener('keydown', function (event) {
                if (['ArrowLeft', 'ArrowRight', 'Home', 'End'].indexOf(event.key) < 0) return;
                event.preventDefault();
                var btns = Array.prototype.slice.call(event.currentTarget.querySelectorAll('button'));
                var at = btns.indexOf(event.target);
                at = event.key === 'Home' ? 0 : event.key === 'End' ? btns.length - 1 : (at + (event.key === 'ArrowRight' ? 1 : -1) + btns.length) % btns.length;
                btns[at].click();
                btns[at].focus();
            });
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', setPresentation);
    } else {
        setPresentation();
    }
})();
