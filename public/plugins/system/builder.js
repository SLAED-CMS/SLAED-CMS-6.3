/* The editing layer of a page of the site builder inside the frame of its screen (admin/modules/layout.php), loaded only when the page is drawn in
   the edit mode of the builder for its super administrator. The theme marks its parts: data-sl-pick on a section, a part, a menu item or a block,
   data-sl-text on a text typed in place (data-sl-lines for one of several lines), data-sl-md on a text of the editor window; the template
   [data-sl-build] holds the rings and the toolbar. Messages to the screen: {sl: 'ready'}, {sl: 'pick', sel}, {sl: 'text', sel, path, value},
   {sl: 'act', act, sel}, {sl: 'move', sel, to, after}, {sl: 'open', sel, path}, {sl: 'key', key, code, ctrl, alt, shift}; from it:
   {sl: 'init', sel, names, words}, {sl: 'pick', sel, scroll}, {sl: 'scale', scale} */
(function () {
    'use strict';
    var tpl = document.querySelector('template[data-sl-build]');
    if (window.parent === window || !tpl) return;
    var root = document.documentElement;
    var state = { sel: '', names: {}, typing: null, drag: null, zoom: 1 };
    var layer = tpl.content.cloneNode(true);
    var hover = layer.querySelector('[data-sl-build-hover]');
    var ring = layer.querySelector('[data-sl-build-sel]');
    var bar = layer.querySelector('[data-sl-build-bar]');
    var line = layer.querySelector('[data-sl-build-line]');
    var ghost = layer.querySelector('[data-sl-build-ghost]');
    document.body.appendChild(layer);
    root.setAttribute('data-sl-build', '');

    // Tell the screen what happened on the page; the page is of the same site, so only its origin hears it
    function post(msg) {
        window.parent.postMessage(msg, window.location.origin);
    }

    function getPicked(sel) {
        return sel ? document.querySelector('[data-sl-pick="' + window.CSS.escape(sel) + '"]') : null;
    }

    function getOwner(node) {
        var pick = node.closest('[data-sl-pick]');
        return pick ? pick.getAttribute('data-sl-pick') : '';
    }

    // Lay a ring over a part; a part pinned to the window (the side tabs) is ringed in the window, so its ring stays on it while the page scrolls
    function setRingAt(box, node) {
        if (!node || !node.isConnected || node.closest('[hidden]')) {
            box.hidden = true;
            return;
        }
        var rect = node.getBoundingClientRect();
        var pinned = false;
        for (var one = node; one && one !== document.body && !pinned; one = one.parentElement) pinned = window.getComputedStyle(one).position === 'fixed';
        var left = Math.max(rect.left, 0);
        box.hidden = false;
        box.toggleAttribute('data-sl-build-pinned', pinned);
        box.style.setProperty('--sl-d-box-x', (left + (pinned ? 0 : window.scrollX)) + 'px');
        box.style.setProperty('--sl-d-box-y', (rect.top + (pinned ? 0 : window.scrollY)) + 'px');
        box.style.setProperty('--sl-d-box-w', (rect.right - left) + 'px');
        box.style.setProperty('--sl-d-box-h', rect.height + 'px');
    }

    // The ring of the pick with its name; a section carries the toolbar, its order kept among the sections the page shows and its link only with an anchor
    function setRings() {
        var node = getPicked(state.sel);
        var secs = Array.prototype.slice.call(document.querySelectorAll('[data-sl-pick^="sec:"]'));
        var at = secs.indexOf(node);
        setRingAt(ring, node);
        ring.querySelector('[data-sl-build-name]').textContent = state.names[state.sel] || '';
        bar.hidden = !node || at < 0;
        ring.querySelector('[data-sl-build-act="add"]').hidden = bar.hidden;
        bar.querySelector('[data-sl-build-act="up"]').disabled = at <= 0;
        bar.querySelector('[data-sl-build-act="down"]').disabled = at < 0 || at === secs.length - 1;
        bar.querySelector('[data-sl-build-act="link"]').hidden = !node || !node.id;
        bar.querySelector('[data-sl-build-act="dup"]').hidden = !node || node.hasAttribute('data-sl-build-one');
    }

    // Set the words of the toolbar the screen sends, each button named by its title and its label
    function setWords(words) {
        Object.keys(words || {}).forEach(function (key) {
            document.querySelectorAll('[data-sl-build-word="' + key + '"]').forEach(function (one) {
                if (one.matches('button')) {
                    one.title = words[key];
                    one.setAttribute('aria-label', words[key]);
                } else {
                    one.textContent = words[key];
                }
            });
        });
    }

    function setPick(sel, tell) {
        state.sel = sel;
        hover.hidden = true;
        setRings();
        if (tell) post({ sl: 'pick', sel: sel });
    }

    // The text a click types: the one under the pointer, or the only text of the part clicked beside it, so a click on the padding of a tab still reaches its words
    function getWords(node) {
        var words = node.closest('[data-sl-text]');
        if (words) return words;
        var part = node.closest('[data-sl-pick]');
        var list = part ? part.querySelectorAll('[data-sl-text]') : [];
        return list.length === 1 ? list[0] : null;
    }

    // The words as typed, not as shown: the text nodes and the breaks, never the capitals a theme draws with text-transform
    function getPlain(node) {
        var out = '';
        node.childNodes.forEach(function (one) {
            if (one.nodeType === window.Node.TEXT_NODE) {
                out += one.nodeValue;
            } else if (one.nodeName === 'BR') {
                out += '\n';
            } else if (one.nodeType === window.Node.ELEMENT_NODE) {
                if (/^(DIV|P)$/.test(one.nodeName) && out && out.slice(-1) !== '\n') out += '\n';
                out += getPlain(one);
            }
        });
        return out;
    }

    // Type a text in place with the keys of the quick edit of the site: Esc gives the text back, Enter keeps one line, Ctrl+Enter keeps several
    function setTyping(words) {
        var lines = words.hasAttribute('data-sl-lines');
        var owner = getOwner(words);
        var was = words.innerHTML;
        var read = function () {
            var text = getPlain(words).replace(/\u00a0/g, ' ');
            return lines ? text.replace(/\n+$/, '').trim() : text.replace(/\s*\n\s*/g, ' ').trim();
        };
        var first = read();
        state.typing = words;
        try { words.contentEditable = 'plaintext-only'; } catch (e) { words.contentEditable = 'true'; }
        words.spellcheck = true;
        words.focus();
        var range = document.createRange();
        range.selectNodeContents(words);
        window.getSelection().removeAllRanges();
        window.getSelection().addRange(range);
        var onKey = function (event) {
            if (event.key === 'Escape') {
                event.preventDefault();
                event.stopPropagation();
                words.innerHTML = was;
                words.blur();
            } else if (event.key === 'Enter' && (!lines || event.ctrlKey || event.metaKey)) {
                event.preventDefault();
                words.blur();
            }
        };
        var onBlur = function () {
            var text = read();
            words.removeAttribute('contenteditable');
            words.removeEventListener('keydown', onKey);
            state.typing = null;
            if (text !== first) post({ sl: 'text', sel: owner, path: words.getAttribute('data-sl-text'), value: text });
        };
        words.addEventListener('keydown', onKey);
        words.addEventListener('blur', onBlur, { once: true });
    }

    document.addEventListener('pointerover', function (event) {
        var node = event.target.closest ? event.target.closest('[data-sl-pick]') : null;
        if (!node || node.getAttribute('data-sl-pick') === state.sel || state.drag) {
            hover.hidden = true;
            return;
        }
        setRingAt(hover, node);
        hover.querySelector('[data-sl-build-name]').textContent = state.names[node.getAttribute('data-sl-pick')] || '';
    });

    root.addEventListener('pointerleave', function () { hover.hidden = true; });

    // The first click picks a part, a click on a text of the picked part types it; a link, a button or a form never leaves the page, a summary still opens
    document.addEventListener('click', function (event) {
        var act = event.target.closest('[data-sl-build-act]');
        if (act) {
            event.preventDefault();
            if (!act.disabled) post({ sl: 'act', act: act.getAttribute('data-sl-build-act'), sel: state.sel });
            return;
        }
        if (event.target.closest('[contenteditable]')) {
            if (event.target.closest('a')) event.preventDefault();
            return;
        }
        if (!event.target.closest('summary')) event.preventDefault();
        var node = event.target.closest('[data-sl-pick]');
        if (!node) return;
        var sel = node.getAttribute('data-sl-pick');
        var words = getWords(event.target);
        if (sel === state.sel && words && getOwner(words) === sel) return setTyping(words);
        setPick(sel, true);
    }, true);

    // A double click types a text at once, or opens the editor window of a text of Markdown or of the own HTML
    document.addEventListener('dblclick', function (event) {
        var md = event.target.closest('[data-sl-md]');
        var words = md ? null : getWords(event.target);
        var owner = getOwner(md || words || event.target);
        if (!owner || (!md && (!words || words.isContentEditable))) return;
        event.preventDefault();
        if (owner !== state.sel) setPick(owner, true);
        if (md) return post({ sl: 'open', sel: owner, path: md.getAttribute('data-sl-md') });
        setTyping(words);
    });

    document.addEventListener('submit', function (event) { event.preventDefault(); }, true);

    // A key of the screen pressed on the page goes to the screen, which runs it as its own; a text typed in place and a field keep theirs
    document.addEventListener('keydown', function (event) {
        var ctrl = event.ctrlKey || event.metaKey;
        if (event.defaultPrevented || event.isComposing || state.typing || event.target.closest('input, select, textarea, [contenteditable]')) return;
        var mine = ctrl ? (event.code === 'KeyP' ? event.altKey : ['KeyK', 'KeyZ', 'KeyY', 'KeyD'].indexOf(event.code) >= 0)
            : (event.altKey ? /^Arrow(Up|Down)$/.test(event.key) : event.key === 'Delete');
        if (!mine) return;
        event.preventDefault();
        post({ sl: 'key', key: event.key, code: event.code, ctrl: ctrl, alt: event.altKey, shift: event.shiftKey });
    });

    // The place a dragged section lands: before or after the section under the pointer
    function getSpot(x, y) {
        ghost.hidden = true;
        var at = document.elementFromPoint(x, y);
        ghost.hidden = false;
        var one = at ? at.closest('[data-sl-pick^="sec:"]') : null;
        if (!one) return null;
        var rect = one.getBoundingClientRect();
        return { node: one, to: one.getAttribute('data-sl-pick').slice(4), after: y > rect.top + rect.height / 2 };
    }

    // Drag with the cursor: the grip of the picked section follows the pointer, a line shows where it lands, the drop is told to the screen
    document.addEventListener('pointerdown', function (event) {
        var grip = event.target.closest('[data-sl-build-grip]');
        if (!grip || state.sel.indexOf('sec:') !== 0) return;
        event.preventDefault();
        state.drag = { spot: null };
        ghost.textContent = state.names[state.sel] || '';
        ghost.hidden = false;
        grip.setPointerCapture(event.pointerId);
        root.setAttribute('data-sl-build-drag', '');
    });

    document.addEventListener('pointermove', function (event) {
        if (!state.drag) return;
        ghost.style.setProperty('--sl-d-pointer-x', (event.clientX / state.zoom) + 'px');
        ghost.style.setProperty('--sl-d-pointer-y', (event.clientY / state.zoom) + 'px');
        var spot = getSpot(event.clientX, event.clientY);
        state.drag.spot = spot && 'sec:' + spot.to !== state.sel ? spot : null;
        if (!state.drag.spot) {
            line.hidden = true;
            return;
        }
        var rect = spot.node.getBoundingClientRect();
        line.hidden = false;
        line.style.setProperty('--sl-d-box-x', (rect.left + window.scrollX) + 'px');
        line.style.setProperty('--sl-d-box-y', ((spot.after ? rect.bottom : rect.top) + window.scrollY - 2) + 'px');
        line.style.setProperty('--sl-d-box-w', rect.width + 'px');
        line.style.setProperty('--sl-d-box-h', '4px');
        if (event.clientY < 60) window.scrollBy(0, -12);
        if (event.clientY > window.innerHeight - 60) window.scrollBy(0, 12);
    });

    document.addEventListener('pointerup', function () {
        if (!state.drag) return;
        var spot = state.drag.spot;
        state.drag = null;
        ghost.hidden = true;
        line.hidden = true;
        root.removeAttribute('data-sl-build-drag');
        if (spot) post({ sl: 'move', sel: state.sel, to: spot.to, after: spot.after });
    });

    window.addEventListener('message', function (event) {
        var msg = event.data;
        if (event.origin !== window.location.origin || event.source !== window.parent || !msg || !msg.sl) return;
        if (msg.sl === 'init') {
            state.names = msg.names || {};
            setWords(msg.words);
            setPick(msg.sel || '', false);
        } else if (msg.sl === 'pick' && !state.typing) {
            setPick(msg.sel || '', false);
            var node = getPicked(state.sel);
            if (node && msg.scroll) node.scrollIntoView({ block: 'nearest', behavior: 'instant' });
        } else if (msg.sl === 'scale') {
            state.zoom = Math.min(2, 1 / (Number(msg.scale) || 1));
            root.style.setProperty('--sl-d-zoom', String(state.zoom));
            window.requestAnimationFrame(setRings);
        }
    });

    window.addEventListener('resize', function () { window.requestAnimationFrame(setRings); });
    new window.ResizeObserver(function () { window.requestAnimationFrame(setRings); }).observe(document.body);
    post({ sl: 'ready' });
})();
