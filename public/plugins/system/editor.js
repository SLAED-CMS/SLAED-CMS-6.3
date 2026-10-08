// Author: Eduard Laas
// 2005 - 2026 SLAED
// License: MIT
// Website: slaed.net

(function (win, doc) {
    'use strict';

    var eds = new WeakMap();
    var mods = {};
    var kit = null;
    var seer = null;

    // Read the words and the capsule of the shell from the kit the answer carries, once per page
    function getKit() {
        if (kit) return kit;
        var node = doc.querySelector('template[data-sl-editor-kit]');
        if (!node) return null;
        var words = {};
        try { words = JSON.parse(node.getAttribute('data-sl-editor-words') || '{}'); } catch (err) { words = {}; }
        kit = { words: words, pill: node.content.querySelector('[data-sl-editor-pill]'), draft: node.content.querySelector('[data-sl-editor-draft]') };
        return kit;
    }

    // Put numbers into a phrase of the locale in the order its placeholders name them
    function getPhrase(text, one, two) {
        return String(text || '').replace('%1$s', one).replace('%2$s', two).replace('%s', one);
    }

    // The text an editor holds now, from CodeMirror once it is mounted and from the textarea before
    function getText(ed) {
        return ed.view ? ed.view.state.doc.toString() : ed.area.value;
    }

    // Count the line ends of a text up to a position without cutting the text into pieces
    function getLineCount(text, end) {
        var num = 1;
        var at = text.indexOf('\n');
        while (at !== -1 && at < end) {
            num++;
            at = text.indexOf('\n', at + 1);
        }
        return num;
    }

    // Write the caret, the selection and the size of one editor into its status line
    function setStatus(ed) {
        var words = kit.words;
        var line = 1;
        var col = 1;
        var sel = 0;
        var rows = 1;
        var size = 0;
        if (ed.view) {
            var state = ed.view.state;
            var range = state.selection.main;
            var row = state.doc.lineAt(range.head);
            line = row.number;
            col = range.head - row.from + 1;
            sel = range.to - range.from;
            rows = state.doc.lines;
            size = state.doc.length;
        } else {
            var area = ed.area;
            var text = area.value;
            line = getLineCount(text, area.selectionStart);
            col = area.selectionStart - text.lastIndexOf('\n', area.selectionStart - 1);
            sel = area.selectionEnd - area.selectionStart;
            rows = getLineCount(text, text.length);
            size = text.length;
        }
        ed.pos.textContent = getPhrase(words.pos, line, col) + (sel ? ' · ' + getPhrase(words.sel, sel) : '');
        ed.size.textContent = getPhrase(words.size, rows, size);
    }

    // Mark an editor as changed against the text it was loaded with, and keep the draft of the tab after a pause
    function setChange(ed) {
        var text = getText(ed);
        if (ed.frame.hasAttribute('data-invalid')) setValid(ed);
        ed.frame.toggleAttribute('data-dirty', text !== ed.orig);
        clearTimeout(ed.wait);
        ed.wait = setTimeout(function () { setDraft(ed, text); }, 500);
    }

    // The draft key names the page, the field and its place among fields of the same name, so two forms of one page keep two drafts
    function getDraftKey(area) {
        var name = area.name || area.id;
        var same = Array.prototype.filter.call(doc.querySelectorAll('[data-sl-editor-area]'), function (one) { return (one.name || one.id) === name; });
        return 'slaed-draft:' + win.location.pathname + win.location.search + ':' + name + ':' + same.indexOf(area);
    }

    // Keep the unsent text in the storage of the tab while it differs from the loaded one; a storage that refuses only loses the copy
    function setDraft(ed, text) {
        try {
            if (text === ed.orig) win.sessionStorage.removeItem(ed.key);
            else win.sessionStorage.setItem(ed.key, text);
        } catch (err) {
            return;
        }
    }

    // Read the draft the tab kept for one editor, or null where there is none or the storage is closed
    function getDraft(key) {
        try {
            return win.sessionStorage.getItem(key);
        } catch (err) {
            return null;
        }
    }

    // Offer back the draft of the tab when the same form opens again with another text than the one left behind
    function setDraftOffer(ed) {
        var text = getDraft(ed.key);
        if (text === null || text === ed.area.value) return;
        if (text === ed.orig) return setDraft(ed, text);
        ed.offer = kit.draft.cloneNode(true);
        ed.offer.slText = text;
        ed.card.insertBefore(ed.offer, ed.card.firstChild);
    }

    // Take the offered draft back into the editor or throw it away, and close the offer either way
    function setDraftAnswer(ed, take) {
        var text = ed.offer ? ed.offer.slText : null;
        if (ed.offer) ed.offer.remove();
        ed.offer = null;
        if (take && text !== null) setText(ed, text, '');
        else setDraft(ed, ed.orig);
    }

    // Show a short note in the status line for a moment
    function setNote(ed, text) {
        ed.note.textContent = text;
        clearTimeout(ed.noteWait);
        ed.noteWait = setTimeout(function () { ed.note.textContent = ''; }, 1600);
    }

    // Say that a required text under CodeMirror is empty, which the browser cannot point at with its field hidden
    function setInvalid(ed, ev) {
        if (!ed.view) return;
        ev.preventDefault();
        clearTimeout(ed.noteWait);
        ed.frame.setAttribute('data-invalid', '');
        ed.note.textContent = ed.area.validationMessage;
        ed.view.focus();
    }

    // Take the mark of an empty required text away once something is typed
    function setValid(ed) {
        ed.frame.removeAttribute('data-invalid');
        ed.note.textContent = '';
    }

    // Replace the whole text as one step the undo of the engine can take back, telling the form as typing does
    function setText(ed, text, note) {
        if (ed.view) {
            ed.view.dispatch({ changes: { from: 0, to: ed.view.state.doc.length, insert: text } });
            ed.area.dispatchEvent(new Event('input', { bubbles: true }));
        } else {
            var area = ed.area;
            area.focus();
            area.select();
            if (!doc.execCommand(text === '' ? 'delete' : 'insertText', false, text)) {
                area.value = text;
                area.dispatchEvent(new Event('input', { bubbles: true }));
            }
        }
        if (note) setNote(ed, note);
    }

    // Toggle soft line wrapping in CodeMirror or in the textarea
    function setWrap(ed, btn) {
        var on = btn.getAttribute('aria-pressed') !== 'true';
        btn.setAttribute('aria-pressed', String(on));
        ed.nowrap = !on;
        if (ed.view) ed.view.dispatch({ effects: ed.wrap.reconfigure(on ? ed.cm.EditorView.lineWrapping : []) });
        else ed.area.setAttribute('wrap', on ? 'soft' : 'off');
    }

    // Move an editor between its place in the form and the whole screen, through a view transition where the browser has one
    function setFull(ed, on) {
        var frame = ed.frame;
        var flip = function () {
            frame.toggleAttribute('data-full', on);
            doc.documentElement.classList.toggle('sl-is-locked', on);
            frame.querySelectorAll('[data-sl-editor-act="full"]').forEach(function (btn) { if (win.setExpandButton) win.setExpandButton(btn, on); });
        };
        var done = function () {
            frame.style.viewTransitionName = '';
            if (ed.view) ed.view.requestMeasure();
            (ed.view || ed.area).focus();
        };
        if (!doc.startViewTransition) {
            flip();
            done();
            return;
        }
        frame.style.viewTransitionName = 'sl-editor';
        doc.startViewTransition(flip).finished.then(done, done);
    }

    // Copy the whole text to the clipboard and say whether it worked
    function setCopy(ed) {
        var words = kit.words;
        if (!navigator.clipboard) return setNote(ed, words.noclip);
        navigator.clipboard.writeText(getText(ed)).then(function () { setNote(ed, words.copied); }, function () { setNote(ed, words.noclip); });
    }

    // Run one command of the capsule on an editor
    function setAct(ed, act, btn) {
        if (act === 'full') return setFull(ed, !ed.frame.hasAttribute('data-full'));
        if (act === 'wrap') return setWrap(ed, btn);
        if (act === 'copy') return setCopy(ed);
        if (act === 'reset') return setText(ed, ed.orig, kit.words.restored);
        if (act === 'restore' || act === 'drop') return setDraftAnswer(ed, act === 'restore');
        if (act !== 'undo' && act !== 'redo') return;
        if (ed.view) {
            ed.view.focus();
            ed.cm[act](ed.view);
            return;
        }
        ed.area.focus();
        doc.execCommand(act);
    }

    // Move the capsule by its grip: the pointer drags it, the arrow keys step it, a double click and Home send it back
    function setGrip(pill, grip) {
        var pos = { x: 0, y: 0 };
        var from = null;
        var put = function (x, y) {
            pos.x = x;
            pos.y = y;
            pill.style.setProperty('--sl-d-pill-x', x + 'px');
            pill.style.setProperty('--sl-d-pill-y', y + 'px');
        };
        var keep = function () {
            var box = pill.getBoundingClientRect();
            var dx = Math.max(0, -box.left) - Math.max(0, box.right - win.innerWidth);
            var dy = Math.max(0, -box.top) - Math.max(0, box.bottom - win.innerHeight);
            if (dx || dy) put(pos.x + dx, pos.y + dy);
        };
        var end = function (ev) {
            if (!from || ev.pointerId !== from.id) return;
            from = null;
            pill.removeAttribute('data-moving');
            keep();
        };
        grip.addEventListener('pointerdown', function (ev) {
            if (ev.button !== 0) return;
            ev.preventDefault();
            grip.focus();
            from = { id: ev.pointerId, x: ev.clientX - pos.x, y: ev.clientY - pos.y };
            grip.setPointerCapture(ev.pointerId);
            pill.setAttribute('data-moving', '');
        });
        grip.addEventListener('pointermove', function (ev) {
            if (from && ev.pointerId === from.id) put(ev.clientX - from.x, ev.clientY - from.y);
        });
        grip.addEventListener('pointerup', end);
        grip.addEventListener('pointercancel', end);
        grip.addEventListener('dblclick', function () { put(0, 0); });
        grip.addEventListener('keydown', function (ev) {
            var step = ev.shiftKey ? 32 : 8;
            var move = { ArrowLeft: [-step, 0], ArrowRight: [step, 0], ArrowUp: [0, -step], ArrowDown: [0, step] }[ev.key];
            if (ev.key === 'Home') move = [-pos.x, -pos.y];
            if (!move) return;
            ev.preventDefault();
            put(pos.x + move[0], pos.y + move[1]);
            keep();
        });
    }

    // Load one module of CodeMirror by its versioned address once, resolved against the page and not against this script
    function getModule(url) {
        var full = new URL(url, doc.baseURI).href;
        if (!mods[full]) mods[full] = import(full);
        return mods[full];
    }

    // Give the editable area of CodeMirror what its textarea says: the name, by a caption or by its own text, and whether a text is spell-checked
    function getAreaAttr(area) {
        var ref = area.getAttribute('aria-labelledby');
        var attr = { spellcheck: String(area.spellcheck), autocorrect: area.spellcheck ? 'on' : 'off' };
        if (ref) attr['aria-labelledby'] = ref;
        else attr['aria-label'] = area.getAttribute('aria-label') || '';
        return attr;
    }

    // Mount CodeMirror over the textarea of a frame with the grammar it names; the textarea stays in the form and carries the text on submit
    function setCode(ed) {
        if (ed.load) return ed.load;
        var frame = ed.frame;
        var gram = frame.getAttribute('data-sl-editor-grammar');
        ed.load = Promise.all([getModule(frame.getAttribute('data-sl-editor-core')), gram ? getModule(gram) : null]).then(function (list) {
            if (!frame.isConnected || eds.get(frame) !== ed) return;
            var cm = list[0];
            var area = ed.area;
            var had = doc.activeElement === area;
            var code = frame.hasAttribute('data-sl-editor-code');
            var exts = [
                code ? cm.basicSetup : cm.textSetup,
                cm.syntaxHighlighting(cm.classHighlighter),
                code ? cm.keymap.of([cm.indentWithTab]) : [],
                cm.EditorState.phrases.of(kit.words.phrases || {}),
                cm.EditorView.contentAttributes.of(getAreaAttr(area)),
                cm.EditorView.updateListener.of(function (upd) {
                    if (upd.docChanged) {
                        area.value = upd.state.doc.toString();
                        setChange(ed);
                    }
                    if (upd.docChanged || upd.selectionSet) setStatus(ed);
                })
            ];
            ed.cm = cm;
            ed.wrap = new cm.Compartment();
            exts.push(ed.wrap.of(ed.nowrap ? [] : cm.EditorView.lineWrapping));
            if (list[1]) exts.push(list[1].language());
            ed.view = new cm.EditorView({
                state: cm.EditorState.create({ doc: area.value, selection: { anchor: area.selectionStart, head: area.selectionEnd }, extensions: exts })
            });
            if (area.offsetHeight) frame.style.setProperty('--sl-d-editor-floor', area.offsetHeight + 'px');
            area.parentNode.insertBefore(ed.view.dom, area);
            area.hidden = true;
            if (had) ed.view.focus();
            setStatus(ed);
        }, function () {
            ed.load = null;
        });
        return ed.load;
    }

    // Wake CodeMirror for the frames that come near the screen, so a page of many editors loads none it never shows
    function getSeer() {
        if (seer || !win.IntersectionObserver) return seer;
        seer = new IntersectionObserver(function (list) {
            list.forEach(function (one) {
                var ed = eds.get(one.target);
                if (!one.isIntersecting || !ed) return;
                seer.unobserve(one.target);
                setCode(ed);
            });
        }, { rootMargin: '200px 0px' });
        return seer;
    }

    // Whether a window the frame does not stand in is open over it, which then owns the Escape key
    function checkCovered(frame) {
        return Array.prototype.some.call(doc.querySelectorAll('dialog[open]'), function (one) { return !one.contains(frame); });
    }

    // Take one frame into the runtime: the capsule, the status line, the draft offer and CodeMirror when the frame names its core
    function setFrame(frame) {
        if (eds.has(frame) || !getKit()) return;
        var area = frame.querySelector('[data-sl-editor-area]');
        if (!area) return;
        var pill = kit.pill.cloneNode(true);
        var ed = {
            frame: frame,
            area: area,
            card: frame.querySelector('[data-sl-editor-card]'),
            pos: frame.querySelector('[data-sl-editor-pos]'),
            size: frame.querySelector('[data-sl-editor-size]'),
            note: frame.querySelector('[data-sl-editor-note]'),
            orig: area.defaultValue,
            key: getDraftKey(area),
            view: null,
            load: null,
            nowrap: false
        };
        eds.set(frame, ed);
        pill.setAttribute('aria-label', getPhrase(kit.words.tools, frame.getAttribute('data-sl-editor-name') || ''));
        frame.querySelector('[data-sl-editor-row]').appendChild(pill);
        if (pill.querySelector('[data-sl-editor-act="grip"]')) setGrip(pill, pill.querySelector('[data-sl-editor-act="grip"]'));
        ['input', 'select', 'keyup', 'click', 'focus'].forEach(function (type) {
            area.addEventListener(type, function (ev) {
                if (ev.type === 'input') setChange(ed);
                setStatus(ed);
            });
        });
        area.addEventListener('invalid', function (ev) { setInvalid(ed, ev); });
        frame.addEventListener('keydown', function (ev) {
            if (ev.key !== 'Escape' || ev.defaultPrevented || !frame.hasAttribute('data-full') || checkCovered(frame)) return;
            ev.preventDefault();
            setFull(ed, false);
        });
        frame.setAttribute('data-ready', '');
        frame.toggleAttribute('data-dirty', area.value !== ed.orig);
        setStatus(ed);
        setDraftOffer(ed);
        if (frame.hasAttribute('data-sl-editor-core')) {
            area.addEventListener('focus', function () { setCode(ed); });
            if (getSeer()) seer.observe(frame);
            else setCode(ed);
        }
        if (win.SlaedEditors) win.SlaedEditors.own(frame, function () { setDrop(frame); });
    }

    // Release one frame when its region leaves the page
    function setDrop(frame) {
        var ed = eds.get(frame);
        if (!ed) return;
        if (seer) seer.unobserve(frame);
        if (ed.view) ed.view.destroy();
        if (frame.hasAttribute('data-full')) doc.documentElement.classList.remove('sl-is-locked');
        clearTimeout(ed.wait);
        eds.delete(frame);
    }

    // Take every frame inside a region into the runtime
    function setFrames(root) {
        if (!root || !root.querySelectorAll) return;
        if (root.matches && root.matches('[data-sl-editor]')) setFrame(root);
        root.querySelectorAll('[data-sl-editor]').forEach(setFrame);
    }

    // Take the default text a form reset put back as the loaded one, showing it in CodeMirror too and dropping the draft of the tab
    function setReset(ed) {
        if (!ed || eds.get(ed.frame) !== ed) return;
        ed.orig = ed.area.defaultValue;
        if (ed.view) ed.view.dispatch({ changes: { from: 0, to: ed.view.state.doc.length, insert: ed.area.value } });
        clearTimeout(ed.wait);
        setDraft(ed, ed.orig);
        ed.frame.toggleAttribute('data-dirty', getText(ed) !== ed.orig);
        setStatus(ed);
    }

    // Find the editor of a textarea by its id, for the scripts of a screen that read or replace its text
    function getEditor(id) {
        var area = doc.getElementById(id);
        var frame = area ? area.closest('[data-sl-editor]') : null;
        return frame ? eds.get(frame) || null : null;
    }

    win.SlaedEditor = {
        getText: function (id) {
            var ed = getEditor(id);
            var area = doc.getElementById(id);
            return ed ? getText(ed) : (area ? area.value : null);
        },
        setText: function (id, text) {
            var ed = getEditor(id);
            if (!ed) return false;
            setText(ed, text, '');
            if (ed.view) ed.view.focus();
            return true;
        },
        isDirty: function (id) {
            var ed = getEditor(id);
            return !!ed && getText(ed) !== ed.orig;
        }
    };

    doc.addEventListener('click', function (ev) {
        var btn = ev.target.closest ? ev.target.closest('[data-sl-editor-act]') : null;
        var frame = btn ? btn.closest('[data-sl-editor]') : null;
        var ed = frame ? eds.get(frame) : null;
        if (!ed) return;
        ev.preventDefault();
        setAct(ed, btn.getAttribute('data-sl-editor-act'), btn);
    });

    doc.addEventListener('submit', function (ev) {
        if (!ev.target.querySelectorAll) return;
        ev.target.querySelectorAll('[data-sl-editor]').forEach(function (frame) {
            var ed = eds.get(frame);
            if (!ed) return;
            ed.orig = getText(ed);
            if (ed.view) ed.area.value = ed.orig;
            clearTimeout(ed.wait);
            setDraft(ed, ed.orig);
            frame.removeAttribute('data-dirty');
            if (ed.offer) ed.offer.remove();
            ed.offer = null;
        });
    }, true);

    doc.addEventListener('reset', function (ev) {
        if (!ev.target.querySelectorAll) return;
        var list = Array.prototype.map.call(ev.target.querySelectorAll('[data-sl-editor]'), function (frame) { return eds.get(frame); });
        setTimeout(function () { list.forEach(setReset); }, 0);
    }, true);

    doc.addEventListener('htmx:load',function (ev) { setFrames(ev.detail ? ev.detail.elt : null); });

    if (doc.readyState === 'loading') doc.addEventListener('DOMContentLoaded', function () { setFrames(doc); });
    else setFrames(doc);
})(window, document);
