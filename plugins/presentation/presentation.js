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

    // The rhythm chart: the day is the visits by hour, the week and the month are the visits and hosts by day, all three
    // written by the module. The headline follows the period, and the change is shown only where a like period precedes it
    function setChart(root) {
        if (root.getAttribute('data-sl-pres-ready') === '1') return;
        var face = root.querySelector('canvas');
        var tip = root.querySelector('output');
        var sets = {};
        try { sets = JSON.parse(root.getAttribute('data-sl-pres-series') || '{}'); } catch (err) { sets = {}; }
        if (!face || !tip || !sets.day) return;
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
            if (set.hosts) rows.push(set.hosts);
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
            var tones = [getTone(root, '--sl-primary'), getTone(root, '--sl-accent')];
            var mute = getTone(root, '--sl-text-muted');
            var line = getTone(root, '--sl-border');
            var style = window.getComputedStyle(root);
            pen.font = style.getPropertyValue('--sl-font-micro').trim() + ' ' + style.fontFamily;
            pen.textBaseline = 'middle';
            var left = Math.ceil(pen.measureText(getFigure(top)).width) + 8;
            var right = rect.width - 10;
            var high = 12;
            var low = rect.height - 30;
            var count = rows[0].length;
            geo = { left: left, right: right, count: count };
            var getX = function (i) { return left + (count > 1 ? i / (count - 1) : 0) * (right - left); };
            var getY = function (val) { return low - val / top * (low - high); };
            for (var i = 0; i <= 4; i++) {
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
            for (var t = 0; t < 5; t++) {
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
            tip.hidden = true;
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

    function setPresentation() {
        var list = document.querySelectorAll('[data-sl-pres]');
        for (var i = 0; i < list.length; i++) {
            if (list[i].getAttribute('data-sl-pres') === 'chart') setChart(list[i]);
            else setGallery(list[i]);
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', setPresentation);
    } else {
        setPresentation();
    }
})();
