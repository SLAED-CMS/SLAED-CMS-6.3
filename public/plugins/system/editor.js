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
    var pal = null;
    var dif = null;
    var cells = 4000000;
    var enc = win.TextEncoder ? new win.TextEncoder() : null;
    var uid = 0;
    var voids = /^(area|base|br|col|embed|hr|img|input|link|meta|source|track|wbr)$/i;
    var cmds = { undo: 'undo', redo: 'redo', all: 'selectAll', search: 'openSearchPanel', fold: 'foldAll', unfold: 'unfoldAll', comment: 'toggleComment', hint: 'startCompletion' };

    // Read the words, the capsule and the palette of the shell from the kit the answer carries, once per page
    function getKit() {
        if (kit) return kit;
        var node = doc.querySelector('template[data-sl-editor-kit]');
        if (!node) return null;
        var words = {};
        try { words = JSON.parse(node.getAttribute('data-sl-editor-words') || '{}'); } catch (err) { words = {}; }
        kit = {
            words: words,
            pill: node.content.querySelector('[data-sl-editor-pill]'),
            draft: node.content.querySelector('[data-sl-editor-draft]'),
            hint: node.content.querySelector('[data-sl-editor-hint]'),
            issues: node.content.querySelector('[data-sl-editor-issues]'),
            view: node.content.querySelector('[data-sl-editor-view]'),
            palette: node.content.querySelector('[data-sl-editor-palette]'),
            diff: node.content.querySelector('[data-sl-editor-diff]')
        };
        return kit;
    }

    // Whether an engine serves a command or a key: every word of its need must be a power of the frame
    function checkNeed(can, node) {
        return (node.getAttribute('data-sl-editor-need') || '').split(' ').every(function (one) { return !one || can[one]; });
    }

    // Put values into a phrase of the locale in the order its placeholders name them, a dollar sign in a value taken as it stands
    function getPhrase(text, one, two) {
        return String(text || '').replace('%1$s', function () { return one; }).replace('%2$s', function () { return two; }).replace('%s', function () { return one; });
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

    // A count of bytes in the units the meter of the file window writes
    function getSizeText(size) {
        var unit = ['B', 'KB', 'MB'];
        var num = size;
        var at = 0;
        while (num >= 1024 && at < unit.length - 1) {
            num = num / 1024;
            at++;
        }
        return (at === 0 ? num : num.toFixed(1)) + ' ' + unit[at];
    }

    // Count what is left of the column the text is stored in, in bytes as the column counts them, and say so when the text no longer fits
    function setRoom(ed, text) {
        var used;
        if (!ed.room || !ed.left) return;
        used = enc ? enc.encode(text).length : text.length;
        ed.left.toggleAttribute('data-over', used > ed.room);
        ed.left.textContent = used > ed.room ? getPhrase(kit.words.long, getSizeText(ed.room)) : getPhrase(kit.words.left, getSizeText(ed.room - used));
    }

    // Count the variables of a frame the text already uses, so the status line tells what is still to be placed
    function setVarCount(ed, text) {
        var used;
        if (!ed.vars || !ed.used) return;
        used = ed.vars.filter(function (one) { return text.indexOf(one[0]) >= 0; }).length;
        ed.used.textContent = getPhrase(kit.words.used, used, ed.vars.length);
    }

    // Mark an editor as changed against the text it was loaded with, and keep the draft and the count of the variables after a pause
    function setChange(ed) {
        var text = getText(ed);
        if (ed.frame.hasAttribute('data-invalid')) setValid(ed);
        ed.frame.toggleAttribute('data-dirty', text !== ed.orig);
        setRoom(ed, text);
        clearTimeout(ed.wait);
        ed.wait = setTimeout(function () {
            setDraft(ed, text);
            setVarCount(ed, text);
            setLint(ed);
            setSheet(ed);
        }, 500);
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

    // Replace the whole text as one step the undo of the engine can take back, never joined to the step before it, telling the form as typing does
    function setText(ed, text, note) {
        if (ed.view) {
            ed.view.dispatch({ changes: { from: 0, to: ed.view.state.doc.length, insert: text }, userEvent: 'input' });
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

    // Write a text at the caret as one step the undo takes back, telling the form as typing does; a textarea under a modal window waits for it to close to get the focus
    function addText(ed, text) {
        var area = ed.area;
        var modal = doc.querySelector('dialog:modal');
        if (ed.view) {
            ed.view.dispatch(ed.view.state.replaceSelection(text), { scrollIntoView: true });
            area.dispatchEvent(new Event('input', { bubbles: true }));
            return;
        }
        if (modal && !modal.contains(area)) {
            modal.addEventListener('close', function () { addText(ed, text); }, { once: true });
            return;
        }
        area.focus();
        if (doc.execCommand('insertText', false, text)) return;
        area.setRangeText(text, area.selectionStart, area.selectionEnd, 'end');
        area.dispatchEvent(new Event('input', { bubbles: true }));
    }

    // A picture in the format the field stores: Markdown, or the tag of the parser, which reads no Markdown picture in a plain text
    function addImage(ed, url, alt) {
        var text = String(alt || '').replace(/\s+/g, ' ').trim();
        var src = String(url || '');
        if (ed.frame.getAttribute('data-sl-editor-lang') !== 'markdown') {
            addText(ed, '[img alt=' + (text.replace(/[^\p{L}0-9_\-. "]+/gu, ' ').replace(/\s+/g, ' ').trim() || 'image') + ']' + src + '[/img]');
            return;
        }
        addText(ed, '![' + text.replace(/[[\]\\]/g, '') + '](' + src.replace(/[ ()]/g, function (one) { return { ' ': '%20', '(': '%28', ')': '%29' }[one]; }) + ')');
    }

    // The selection and the whole text of an editor in one shape for CodeMirror and for the textarea
    function getRange(ed) {
        var sel;
        if (ed.view) {
            sel = ed.view.state.selection.main;
            return { from: sel.from, to: sel.to, text: ed.view.state.doc.toString() };
        }
        return { from: ed.area.selectionStart, to: ed.area.selectionEnd, text: ed.area.value };
    }

    // Replace a stretch of the text as one step the undo takes back and select what the command leaves to be selected
    function setRange(ed, from, to, text, head, tail) {
        var area = ed.area;
        if (ed.view) {
            ed.view.dispatch({ changes: { from: from, to: to, insert: text }, selection: { anchor: head, head: tail }, scrollIntoView: true, userEvent: 'input' });
            ed.view.focus();
            area.dispatchEvent(new Event('input', { bubbles: true }));
            return;
        }
        area.focus();
        area.setSelectionRange(from, to);
        if (from !== to || text !== '') {
            if (!doc.execCommand(text === '' ? 'delete' : 'insertText', false, text)) {
                area.setRangeText(text, from, to, 'end');
                area.dispatchEvent(new Event('input', { bubbles: true }));
            }
        }
        area.setSelectionRange(head, tail);
    }

    // Put a Markdown mark around the selection, or take it away where the selection or its edges carry it; a lone star never reads half of a bold one
    function setInline(ed, mark) {
        var at = getRange(ed);
        var sel = at.text.slice(at.from, at.to);
        var len = mark.length;
        var lone = len === 1;
        var inner = sel.length >= len * 2 && sel.slice(0, len) === mark && sel.slice(-len) === mark;
        var outer = at.from >= len && at.text.slice(at.from - len, at.from) === mark && at.text.slice(at.to, at.to + len) === mark;
        if (inner && !(lone && (sel.charAt(1) === mark || sel.charAt(sel.length - 2) === mark))) {
            return setRange(ed, at.from, at.to, sel.slice(len, -len), at.from, at.to - len * 2);
        }
        if (outer && !(lone && (at.text.charAt(at.from - 2) === mark || at.text.charAt(at.to + 1) === mark))) {
            return setRange(ed, at.from - len, at.to + len, sel, at.from - len, at.to - len);
        }
        setRange(ed, at.from, at.to, mark + sel + mark, at.from + len, at.to + len);
    }

    // Mark the selection as code: inline inside one line, a fenced block on lines of its own when it runs over several
    function setCodeMark(ed) {
        var at = getRange(ed);
        var sel = at.text.slice(at.from, at.to).replace(/\n$/, '');
        var lead;
        var tail;
        if (sel.indexOf('\n') < 0) return setInline(ed, '`');
        lead = (at.from > 0 && at.text.charAt(at.from - 1) !== '\n') ? '\n' : '';
        tail = (at.to < at.text.length && at.text.charAt(at.to) !== '\n') ? '\n' : '';
        setRange(ed, at.from, at.to, lead + '```\n' + sel + '\n```' + tail, at.from + lead.length + 4, at.from + lead.length + 4 + sel.length);
    }

    // Make the selection a Markdown link with the address selected to be typed over; a selected address becomes the target and the caret waits for its text
    function setLink(ed) {
        var at = getRange(ed);
        var sel = at.text.slice(at.from, at.to);
        var head;
        if (/^(https?:\/\/|www\.)\S+$/i.test(sel)) return setRange(ed, at.from, at.to, '[](' + sel + ')', at.from + 1, at.from + 1);
        if (sel === '') return setRange(ed, at.from, at.to, '[](url)', at.from + 1, at.from + 1);
        head = at.from + sel.length + 3;
        setRange(ed, at.from, at.to, '[' + sel + '](url)', head, head + 3);
    }

    // Start every line of the selection with the mark of a list or a quote, or take it away where every line carries it
    function setLines(ed, mark) {
        var at = getRange(ed);
        var from = at.text.lastIndexOf('\n', at.from - 1) + 1;
        var last = (at.to > at.from && at.text.charAt(at.to - 1) === '\n') ? at.to - 1 : at.to;
        var end = at.text.indexOf('\n', last);
        var old;
        var rows;
        var off;
        var out;
        var pos;
        if (end < 0) end = at.text.length;
        old = at.text.slice(from, end);
        rows = old.split('\n');
        off = rows.some(function (one) { return one !== ''; }) && rows.every(function (one) { return one === '' || one.indexOf(mark) === 0; });
        out = rows.map(function (one) {
            if (off) return one.indexOf(mark) === 0 ? one.slice(mark.length) : one;
            return (one === '' && rows.length > 1) ? one : mark + one;
        }).join('\n');
        if (at.from !== at.to) return setRange(ed, from, end, out, from, from + out.length);
        pos = Math.max(from, at.from + out.length - old.length);
        setRange(ed, from, end, out, pos, pos);
    }

    // Carry a Markdown list or quote on to the next line as CodeMirror does, or end it on an empty item; CodeMirror has this of its own
    function setListEnter(ed, ev) {
        var area = ed.area;
        var at = area.selectionStart;
        var text = area.value;
        var from;
        var end;
        var hit;
        var next;
        if (ev.key !== 'Enter' || ev.shiftKey || ev.ctrlKey || ev.metaKey || ev.altKey || ev.isComposing) return;
        if (ed.view || !ed.can.markdown || at !== area.selectionEnd) return;
        from = text.lastIndexOf('\n', at - 1) + 1;
        end = text.indexOf('\n', at);
        if (end < 0) end = text.length;
        hit = /^([ \t]*)(?:([-*+])|(\d+)([.)]))[ \t]+(\[[ xX]\][ \t]+)?|^([ \t]*)>[ \t]?/.exec(text.slice(from, end));
        if (!hit || at < from + hit[0].length) return;
        ev.preventDefault();
        if (text.slice(from + hit[0].length, end).trim() === '') return setRange(ed, from, end, '', from, from);
        if (hit[6] !== undefined) next = hit[6] + '> ';
        else next = hit[1] + (hit[2] || (parseInt(hit[3], 10) + 1) + hit[4]) + ' ' + (hit[5] ? '[ ] ' : '');
        setRange(ed, at, at, '\n' + next, at + 1 + next.length, at + 1 + next.length);
    }

    // The keys a frame answers before its engine: Ctrl+K the palette, Ctrl+B and Ctrl+I a Markdown mark, Ctrl+Space the variables of a textarea, Ctrl+Shift+M the findings
    function setKeys(ed, ev) {
        var act = '';
        var text = ev.target === ed.area || (ed.view && ed.view.contentDOM.contains(ev.target));
        if (!(ev.ctrlKey || ev.metaKey) || ev.altKey || ev.isComposing) return;
        if (ev.shiftKey) act = (ev.code === 'KeyM' && ed.can.lint) ? 'lint' : '';
        else if (ev.code === 'KeyK') act = 'palette';
        else if (text && ev.code === 'Space' && ed.can.vars && !ed.view) act = 'hint';
        else if (text && ed.can.markdown) act = { KeyB: 'bold', KeyI: 'italic' }[ev.code] || '';
        if (!act) return;
        ev.preventDefault();
        ev.stopPropagation();
        setAct(ed, act, null);
    }

    // The element that holds the focus while the variables are shown and names the chosen one to a screen reader
    function getHintOwner(ed) {
        return ed.view ? ed.view.contentDOM : ed.area;
    }

    // The list of the variables of one frame, made from the kit the first time it opens and standing in its card
    function getHint(ed) {
        var list;
        var proto;
        if (ed.hint) return ed.hint;
        list = kit.hint.cloneNode(true);
        proto = list.firstElementChild;
        list.id = 'sl-editor-hint-' + (++uid);
        ed.vars.forEach(function (one, i) {
            var row = proto.cloneNode(true);
            row.id = list.id + '-' + i;
            row.setAttribute('data-sl-editor-var', one[0]);
            row.querySelector('code').textContent = one[0];
            row.querySelector('span').textContent = one[1];
            list.appendChild(row);
        });
        proto.remove();
        list.addEventListener('mousedown', function (ev) { ev.preventDefault(); });
        list.addEventListener('click', function (ev) {
            var row = ev.target.closest('[data-sl-editor-var]');
            if (row) addVar(ed, row.getAttribute('data-sl-editor-var'));
        });
        ed.card.appendChild(list);
        ed.hint = list;
        return list;
    }

    // Where a list stands under the caret inside the card: CodeMirror tells it, a textarea is measured through a hidden twin with the same box and face
    function getCaretPlace(ed, pos) {
        var card = ed.card.getBoundingClientRect();
        var area = ed.area;
        var css;
        var probe;
        var mark;
        var box;
        var at;
        if (ed.view) {
            at = ed.view.coordsAtPos(pos);
            return at ? { x: at.left - card.left, y: at.bottom - card.top } : { x: 0, y: 0 };
        }
        css = win.getComputedStyle(area);
        probe = doc.createElement('div');
        ['boxSizing', 'width', 'paddingTop', 'paddingRight', 'paddingBottom', 'paddingLeft', 'borderTopWidth', 'borderRightWidth', 'borderBottomWidth',
            'borderLeftWidth', 'fontFamily', 'fontSize', 'fontWeight', 'lineHeight', 'letterSpacing', 'tabSize', 'wordSpacing', 'overflowWrap'].forEach(function (prop) {
            probe.style[prop] = css[prop];
        });
        probe.style.cssText += ';position:absolute;visibility:hidden;top:0;left:-9999px;border-style:solid;white-space:'
            + (area.getAttribute('wrap') === 'off' ? 'pre' : 'pre-wrap');
        probe.textContent = area.value.slice(0, pos);
        mark = doc.createElement('span');
        mark.textContent = area.value.slice(pos) || '.';
        probe.appendChild(mark);
        doc.body.appendChild(probe);
        box = area.getBoundingClientRect();
        at = {
            x: box.left - card.left + mark.offsetLeft - area.scrollLeft,
            y: box.top - card.top + mark.offsetTop - area.scrollTop + (parseFloat(css.lineHeight) || parseFloat(css.fontSize) * 1.2)
        };
        probe.remove();
        return at;
    }

    // Open the list of variables at a place of the card, narrowed to the words that begin as typed, keeping the chosen one while it is still shown
    function setHint(ed, from, want, place) {
        var list = getHint(ed);
        var owner = getHintOwner(ed);
        var text = getText(ed);
        var keep = list.querySelector('[aria-selected="true"]');
        var first = null;
        list.querySelectorAll('[data-sl-editor-var]').forEach(function (row) {
            var key = row.getAttribute('data-sl-editor-var');
            row.hidden = key.indexOf(want) !== 0;
            row.toggleAttribute('data-used', text.indexOf(key) >= 0);
            if (!row.hidden && !first) first = row;
        });
        if (!first) return setHintShut(ed);
        ed.hintFrom = from;
        list.style.setProperty('--sl-d-hint-x', '0px');
        list.hidden = false;
        list.style.setProperty('--sl-d-hint-x', Math.max(0, Math.min(place.x, ed.card.clientWidth - list.offsetWidth)) + 'px');
        list.style.setProperty('--sl-d-hint-y', Math.max(0, place.y) + 'px');
        owner.setAttribute('aria-controls', list.id);
        ed.frame.querySelectorAll('[data-sl-editor-act="vars"]').forEach(function (btn) { btn.setAttribute('aria-expanded', 'true'); });
        setHintActive(ed, keep && !keep.hidden ? keep : first);
    }

    // Choose one variable of the list; the focus stays in the text, which names the chosen one to a screen reader
    function setHintActive(ed, row) {
        ed.hint.querySelectorAll('[aria-selected]').forEach(function (one) { one.removeAttribute('aria-selected'); });
        row.setAttribute('aria-selected', 'true');
        getHintOwner(ed).setAttribute('aria-activedescendant', row.id);
        row.scrollIntoView({ block: 'nearest' });
    }

    // Step the chosen variable up or down through the shown ones, round from the end to the start
    function setHintStep(ed, step) {
        var rows = Array.prototype.filter.call(ed.hint.querySelectorAll('[data-sl-editor-var]'), function (one) { return !one.hidden; });
        var now = rows.indexOf(ed.hint.querySelector('[aria-selected="true"]'));
        setHintActive(ed, rows[(now + step + rows.length) % rows.length]);
    }

    // Close the list of variables and forget the word it was narrowed by
    function setHintShut(ed) {
        var owner;
        if (!ed.hint || ed.hint.hidden) return;
        owner = getHintOwner(ed);
        ed.hint.hidden = true;
        ed.hintFrom = null;
        owner.removeAttribute('aria-activedescendant');
        owner.removeAttribute('aria-controls');
        ed.frame.querySelectorAll('[data-sl-editor-act="vars"]').forEach(function (btn) { btn.setAttribute('aria-expanded', 'false'); });
    }

    // Follow the caret of a textarea: open the list after a bracket being typed, narrow it, close it once the caret leaves the word; only caret keys count
    function setHintType(ed, ev) {
        var area = ed.area;
        var at = area.selectionStart;
        var head;
        if (ev.type === 'keyup' && !/^(Arrow(Left|Right|Up|Down)|Home|End|Page(Up|Down))$/.test(ev.key)) return;
        if (ev.type === 'keyup' && /^Arrow(Up|Down)$/.test(ev.key) && ed.hint && !ed.hint.hidden) return;
        head = at === area.selectionEnd ? /\[\w*$/.exec(area.value.slice(Math.max(0, at - 32), at)) : null;
        if (!head) return setHintShut(ed);
        setHint(ed, at - head[0].length, head[0], getCaretPlace(ed, at - head[0].length));
    }

    // Show every variable from the capsule, the palette or Ctrl+Space: under the button that asked, else at the caret over a word begun after a bracket
    function setHintOpen(ed, btn) {
        var at = getRange(ed);
        var head = at.from === at.to ? /\[\w*$/.exec(at.text.slice(Math.max(0, at.from - 32), at.from)) : null;
        var from = head ? at.from - head[0].length : null;
        var card;
        var box;
        if (ed.hint && !ed.hint.hidden) return setHintShut(ed);
        (ed.view || ed.area).focus();
        if (!btn || !btn.offsetParent) return setHint(ed, from, head ? head[0] : '', getCaretPlace(ed, from === null ? at.from : from));
        card = ed.card.getBoundingClientRect();
        box = btn.getBoundingClientRect();
        setHint(ed, from, head ? head[0] : '', { x: box.left - card.left, y: 0 });
    }

    // The keys of an open list of variables, heard before the engine: the arrows choose, Enter and Tab insert, Escape closes
    function setHintKeys(ed, ev) {
        var list = ed.hint;
        if (!list || list.hidden || ev.isComposing || ev.ctrlKey || ev.metaKey || ev.altKey || ev.shiftKey) return;
        if (ev.key === 'ArrowDown' || ev.key === 'ArrowUp') setHintStep(ed, ev.key === 'ArrowDown' ? 1 : -1);
        else if (ev.key === 'Enter' || ev.key === 'Tab') addVar(ed, list.querySelector('[aria-selected="true"]').getAttribute('data-sl-editor-var'));
        else if (ev.key === 'Escape') setHintShut(ed);
        else return;
        ev.preventDefault();
        ev.stopPropagation();
    }

    // Write a variable at the caret as one step of undo, over the word typed after a bracket and over the closing bracket that waited behind it
    function addVar(ed, key) {
        var at = getRange(ed);
        var from = ed.hintFrom;
        var to = at.to;
        setHintShut(ed);
        if (from === null || from === undefined) from = at.from;
        else if (at.text.charAt(to) === ']') to++;
        setRange(ed, from, to, key, from + key.length, from + key.length);
    }

    // Mark the known variables in CodeMirror, so a template shows what the parser fills in
    function getVarMarks(cm, ed) {
        var deco = new cm.MatchDecorator({
            regexp: new RegExp(ed.vars.map(function (one) { return one[0].replace(/[[\]]/g, '\\$&'); }).join('|'), 'g'),
            decoration: cm.Decoration.mark({ class: 'sl-editor-var' })
        });
        return cm.ViewPlugin.define(function (view) {
            return {
                marks: deco.createDeco(view),
                update: function (upd) { this.marks = deco.updateDeco(upd, this.marks); }
            };
        }, { decorations: function (one) { return one.marks; } });
    }

    // Offer the variables in CodeMirror after a bracket or on Ctrl+Space, taking over the closing bracket the editor put in by itself
    function getVarList(ed, ctx) {
        var word = ctx.matchBefore(/\[\w*\]?/);
        var shut;
        if (!word && !ctx.explicit) return null;
        if (!word) word = { from: ctx.pos, text: '' };
        shut = word.text !== '' && !/\]$/.test(word.text) && ctx.state.sliceDoc(ctx.pos, ctx.pos + 1) === ']';
        return {
            from: word.from,
            to: shut ? ctx.pos + 1 : ctx.pos,
            options: ed.vars.map(function (one) { return { label: one[0], detail: one[1], type: 'variable' }; }),
            validFor: /^\[\w*\]?$/
        };
    }

    // The language data that hands the variables to the completion, one source for the life of the editor, since CodeMirror knows a source by its identity
    function getVarData(ed) {
        var data = [{ autocomplete: function (ctx) { return getVarList(ed, ctx); } }];
        return function () { return data; };
    }

    // Count the edits between two words, so an unknown variable can name the known one it was meant to be
    function getDistance(src, dst) {
        var row = [];
        var prev;
        var keep;
        for (var k = 0; k <= dst.length; k++) row.push(k);
        for (var i = 1; i <= src.length; i++) {
            prev = row[0];
            row[0] = i;
            for (var j = 1; j <= dst.length; j++) {
                keep = row[j];
                row[j] = Math.min(row[j] + 1, row[j - 1] + 1, prev + (src.charAt(i - 1) === dst.charAt(j - 1) ? 0 : 1));
                prev = keep;
            }
        }
        return row[dst.length];
    }

    // The known variable nearest to an unknown one, or an empty word where none is three edits or fewer away
    function getNearVar(ed, word) {
        var best = '';
        var gap = 4;
        ed.vars.forEach(function (one) {
            var far = getDistance(word, one[0]);
            if (far < gap) {
                gap = far;
                best = one[0];
            }
        });
        return best;
    }

    // A tag left open, its fix closing it where its parent closes or at the end of the text
    function addOpenTag(words, out, open, at) {
        var shut = '</' + open.name + '>';
        out.push({ from: open.from, to: open.to, tone: 'error', text: getPhrase(words.open, '<' + open.name + '>'),
            fix: { label: getPhrase(words.fixshut, shut), insert: shut, at: at } });
    }

    // What a template gets wrong, each finding with its place, its weight and its fix of one click; the variables are checked where the frame has them
    function getIssues(ed, text) {
        var words = kit.words.lint;
        var out = [];
        var stack = [];
        var known = ed.vars ? ed.vars.map(function (one) { return one[0]; }) : [];
        var re = /\[(\w+)\]/g;
        var hit;
        var near;
        var tag;
        var name;
        var end;
        var tail;
        var bare;
        var from;
        while (ed.vars && (hit = re.exec(text))) {
            if (known.indexOf(hit[0]) >= 0) continue;
            near = getNearVar(ed, hit[0]);
            out.push({ from: hit.index, to: hit.index + hit[0].length, tone: 'error', text: getPhrase(near ? words.near : words.unknown, hit[0], near),
                fix: near ? { label: getPhrase(words.fixvar, near), insert: near } : null });
        }
        re = /<\/?([a-zA-Z][\w-]*)([^>]*)>/g;
        while ((tag = re.exec(text))) {
            name = tag[1].toLowerCase();
            end = tag.index + tag[0].length;
            if (tag[0].charAt(1) === '/') {
                from = stack.map(function (one) { return one.name; }).lastIndexOf(name);
                if (from >= 0) stack.splice(from).slice(1).forEach(function (open) { addOpenTag(words, out, open, tag.index); });
                else out.push({ from: tag.index, to: end, tone: 'error', text: getPhrase(words.shut, '</' + name + '>'), fix: { label: words.fixdel, insert: '' } });
                continue;
            }
            tail = tag.index + 1 + name.length;
            if (name === 'img' && !/\salt=/i.test(tag[2])) {
                bare = known.indexOf('[title]') >= 0 ? 'alt="[title]"' : 'alt=""';
                out.push({ from: tag.index, to: tail, tone: 'warning', text: words.alt, fix: { label: getPhrase(words.fixadd, bare), insert: ' ' + bare, at: tail } });
            }
            if (/target=["']?_blank/i.test(tag[2]) && !/rel=["'][^"']*noopener/i.test(tag[2])) {
                bare = 'rel="noopener"';
                out.push({ from: tag.index, to: tail, tone: 'warning', text: words.blank, fix: { label: getPhrase(words.fixadd, bare), insert: ' ' + bare, at: tail } });
            }
            bare = /=(\[\w+\])/g;
            while ((hit = bare.exec(tag[2]))) {
                from = tail + hit.index + 1;
                out.push({ from: from, to: from + hit[1].length, tone: 'info', text: words.quot, fix: { label: words.fixquot, insert: '"' + hit[1] + '"' } });
            }
            if (!voids.test(name) && tag[0].slice(-2) !== '/>') stack.push({ name: name, from: tag.index, to: end });
        }
        stack.forEach(function (open) { addOpenTag(words, out, open, text.length); });
        from = known.indexOf('[src]');
        if (from >= 0 && text.trim() !== '' && text.indexOf('[src]') < 0) {
            out.push({ from: 0, to: Math.min(text.length, 1), tone: 'warning', text: getPhrase(words.src, '[src]', ed.vars[from][1]), fix: null });
        }
        return out.sort(function (one, two) { return one.from - two.from; });
    }

    // Lay a template out one tag a line, indenting what nests
    function getPrettyText(text) {
        var depth = 0;
        return text.replace(/>\s*</g, '>\n<').split('\n').map(function (row) {
            var tag = row.trim();
            var open = /^<([a-zA-Z][\w-]*)/.exec(tag);
            var out;
            if (tag.indexOf('</') === 0) depth = Math.max(0, depth - 1);
            out = new Array(depth + 1).join('  ') + tag;
            if (open && !voids.test(open[1]) && tag.slice(-2) !== '/>' && !new RegExp('</' + open[1] + '>\\s*$', 'i').test(tag)) depth++;
            return out;
        }).join('\n');
    }

    // Join a laid out template back into one line
    function getFlatText(text) {
        return text.replace(/>\s*\n\s*</g, '><').replace(/\s*\n\s*/g, ' ').trim();
    }

    // Check the text of a textarea and write what was found; CodeMirror checks through its own linter once it has mounted
    function setLint(ed) {
        if (ed.can.lint && !ed.view) setIssues(ed, getIssues(ed, getText(ed)));
    }

    // Write the findings into the rail, the badge and the open list of a frame
    function setIssues(ed, found) {
        var errs = found.filter(function (one) { return one.tone === 'error'; }).length;
        var tone = errs ? 'error' : (found.length ? 'warn' : 'ok');
        ed.issues = found;
        ed.frame.setAttribute('data-lint', tone);
        ed.badge.querySelectorAll('[data-sl-editor-tone]').forEach(function (one) { one.hidden = one.getAttribute('data-sl-editor-tone') !== tone; });
        ed.badge.setAttribute('aria-disabled', String(!found.length));
        ed.badge.querySelector('[data-sl-editor-lint-text]').textContent = found.length ? getPhrase(kit.words.issues, errs, found.length - errs) : kit.words.nolint;
        if (ed.list && !ed.list.hidden) setIssueList(ed);
    }

    // Fill the list of findings from the kit, a row of the tone of each one with its place and its fix; an empty check closes it
    function setIssueList(ed) {
        var text = getText(ed);
        if (!ed.issues.length) return setIssueShut(ed);
        ed.list.textContent = '';
        ed.issues.forEach(function (one, i) {
            var row = kit.issues.querySelector('[data-sl-editor-tone="' + one.tone + '"]').cloneNode(true);
            var fix = row.querySelector('[data-sl-editor-fix]');
            var col = one.from - text.lastIndexOf('\n', one.from - 1);
            row.querySelector('[data-sl-editor-issue-text]').textContent = one.text;
            row.querySelector('[data-sl-editor-issue-pos]').textContent = getPhrase(kit.words.pos, getLineCount(text, one.from), col);
            row.querySelector('[data-sl-editor-go]').setAttribute('data-sl-editor-go', String(i));
            if (one.fix) {
                fix.setAttribute('data-sl-editor-fix', String(i));
                fix.querySelector('span').textContent = one.fix.label;
            } else fix.remove();
            ed.list.appendChild(row);
        });
    }

    // Open or close the list of findings under the text, from the badge, the palette or Ctrl+Shift+M
    function setIssueOpen(ed) {
        if (ed.list && !ed.list.hidden) return setIssueShut(ed);
        if (!ed.issues.length) return;
        if (!ed.list) {
            ed.list = kit.issues.cloneNode(false);
            ed.list.id = 'sl-editor-issues-' + (++uid);
            ed.badge.setAttribute('aria-controls', ed.list.id);
            ed.list.addEventListener('click', function (ev) { setIssueClick(ed, ev); });
            ed.card.insertBefore(ed.list, ed.card.querySelector('[data-sl-editor-status]'));
        }
        ed.list.hidden = false;
        ed.badge.setAttribute('aria-expanded', 'true');
        setIssueList(ed);
    }

    // Close the list of findings
    function setIssueShut(ed) {
        if (!ed.list) return;
        ed.list.hidden = true;
        ed.badge.setAttribute('aria-expanded', 'false');
    }

    // A row of the list selects the place of its finding, its button applies the fix
    function setIssueClick(ed, ev) {
        var btn = ev.target.closest('[data-sl-editor-go], [data-sl-editor-fix]');
        var one;
        if (!btn) return;
        ev.preventDefault();
        one = ed.issues[parseInt(btn.getAttribute('data-sl-editor-go') || btn.getAttribute('data-sl-editor-fix'), 10)];
        if (!one) return;
        if (btn.hasAttribute('data-sl-editor-fix')) return setIssueFix(ed, one.from, one.text);
        if (ed.view) {
            ed.view.dispatch({ selection: { anchor: one.from, head: one.to }, scrollIntoView: true });
            ed.view.focus();
            return;
        }
        ed.area.focus();
        ed.area.setSelectionRange(one.from, one.to);
    }

    // Apply the fix of a finding found again in the text as it stands now, so a list a pause behind cannot write into a moved place
    function setIssueFix(ed, from, text) {
        var one = getIssues(ed, getText(ed)).filter(function (row) { return row.from === from && row.text === text && row.fix; })[0];
        var at;
        if (!one) return setLint(ed);
        at = one.fix.at === undefined ? null : one.fix.at;
        if (at === null) setRange(ed, one.from, one.to, one.fix.insert, one.from + one.fix.insert.length, one.from + one.fix.insert.length);
        else setRange(ed, at, at, one.fix.insert, at + one.fix.insert.length, at + one.fix.insert.length);
        setLint(ed);
    }

    // The CodeMirror lint source of a frame: marks and fixes in the text, the same findings in the badge and the list
    function getLintSource(ed) {
        return function (view) {
            var found = getIssues(ed, view.state.doc.toString());
            setIssues(ed, found);
            return found.map(function (one) {
                return {
                    from: one.from,
                    to: one.to,
                    severity: one.tone,
                    message: one.text,
                    actions: one.fix ? [{ name: one.fix.label, apply: function (view, from) { setIssueFix(ed, from, one.text); } }] : []
                };
            });
        };
    }

    // The adapter the file window and the emoji panel write through: the five calls they make of Toast UI, answered over a textarea or a CodeMirror view
    function getPort(ed) {
        if (ed.port) return ed.port;
        ed.port = {
            focus: function () { (ed.view || ed.area).focus(); },
            insertText: function (text) { addText(ed, text); },
            exec: function (name, data) { if (name === 'addImage' && data) addImage(ed, data.imageUrl, data.altText); },
            getMarkdown: function () { return getText(ed); },
            addHook: function (name, run) { if (name === 'addImageBlobHook') ed.hook = run; }
        };
        return ed.port;
    }

    // Bind the file window to an editor the first time it is wanted, by when its runtime has loaded in whatever order the scripts came
    function setFileKit(ed) {
        if (ed.bound) return true;
        if (!ed.files || !win.SlaedFileManager) return false;
        win.SlaedFileManager.addUpload(ed.area.id, getPort(ed), ed.files);
        ed.bound = true;
        return true;
    }

    // Open or close the file window of an editor from its folder button
    function setFileWindow(ed) {
        if (setFileKit(ed)) win.SlaedFileManager.addPanel(ed.area.id);
    }

    // The images a paste or a drop carries; while a drag is under way only their kinds can be read, so the items stand in for the files
    function getImages(data) {
        var list;
        if (!data) return [];
        list = Array.prototype.slice.call(data.files || []);
        if (!list.length) list = Array.prototype.filter.call(data.items || [], function (one) { return one.kind === 'file'; });
        return list.filter(function (one) { return /^image\//.test(one.type); });
    }

    // Hand the images of a paste or a drop to the file window as Toast UI hands them to its hook; a paste carrying rich text, as Word and Excel give it, stays text there too
    function setImages(ed, ev) {
        var list = getImages(ev.clipboardData || ev.dataTransfer);
        var rich = ev.clipboardData && Array.prototype.some.call(ev.clipboardData.items || [], function (one) { return one.kind === 'string' && one.type === 'text/rtf'; });
        var at;
        if (!list.length || rich) return;
        ev.preventDefault();
        ev.stopPropagation();
        if (!setFileKit(ed) || !ed.hook) return;
        if (ev.type === 'drop' && ed.view) {
            at = ed.view.posAtCoords({ x: ev.clientX, y: ev.clientY });
            if (at !== null) ed.view.dispatch({ selection: { anchor: at } });
        }
        list.forEach(function (file) {
            ed.hook(file, function (url, alt) { addImage(ed, url, alt); });
        });
    }

    // Let an image be dropped on a text that has a file window, which the browser would otherwise open in place of the page
    function setImageDrag(ed, ev) {
        if (!getImages(ev.dataTransfer).length) return;
        ev.preventDefault();
        ev.stopPropagation();
        ev.dataTransfer.dropEffect = 'copy';
    }

    // Open the emoji panel at the button of an editor, loading its script and the words of the locale on the first press
    function setEmoji(ed, btn) {
        var root = doc.querySelector('[data-sl-emoji-src]');
        var src = [];
        if (!root || !win.SlaedEditors) return;
        try { src = JSON.parse(root.getAttribute('data-sl-emoji-src') || '[]'); } catch (err) { src = []; }
        win.SlaedEditors.load([], src);
        win.SlaedEditors.ready(function () {
            if (win.SlaedEmoji && eds.get(ed.frame) === ed) win.SlaedEmoji.setPanel(ed.area.id, getPort(ed), btn);
        });
    }

    // Toggle soft line wrapping in CodeMirror or in the textarea, from the capsule or from the palette alike
    function setWrap(ed) {
        ed.nowrap = !ed.nowrap;
        ed.frame.querySelectorAll('[data-sl-editor-act="wrap"]').forEach(function (btn) { btn.setAttribute('aria-pressed', String(!ed.nowrap)); });
        if (ed.view) ed.view.dispatch({ effects: ed.wrap.reconfigure(ed.nowrap ? [] : ed.cm.EditorView.lineWrapping) });
        else ed.area.setAttribute('wrap', ed.nowrap ? 'off' : 'soft');
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

    // Run one command of the capsule or of the palette on an editor; a command only CodeMirror has waits for it to mount
    function setAct(ed, act, btn) {
        var md = ed.can.markdown;
        if (act === 'full') return setFull(ed, !ed.frame.hasAttribute('data-full'));
        if (act === 'wrap') return setWrap(ed);
        if (act === 'copy') return setCopy(ed);
        if (act === 'reset') return setText(ed, ed.orig, kit.words.restored);
        if (act === 'restore' || act === 'drop') return setDraftAnswer(ed, act === 'restore');
        if (act === 'files') return setFileWindow(ed);
        if (act === 'emoji') return setEmoji(ed, btn || ed.frame.querySelector('[data-sl-editor-act="emoji"]') || ed.card);
        if (act === 'palette') return setPalette(ed);
        if (act === 'diff') return setDiff(ed);
        if (ed.can.preview && act === 'preview') return setPreview(ed);
        if (ed.can.lint && act === 'lint') return setIssueOpen(ed);
        if (ed.can.lint && (act === 'pretty' || act === 'flat')) return setText(ed, (act === 'pretty' ? getPrettyText : getFlatText)(getText(ed)), '');
        if (md && (act === 'bold' || act === 'italic')) return setInline(ed, act === 'bold' ? '**' : '*');
        if (md && act === 'code') return setCodeMark(ed);
        if (md && act === 'link') return setLink(ed);
        if (md && (act === 'list' || act === 'quote')) return setLines(ed, act === 'list' ? '- ' : '> ');
        if (act === 'hint' && !ed.can.cm) return setHintOpen(ed, null);
        if (!cmds[act] && act !== 'vars') return;
        if (!ed.view && ed.can.cm && act !== 'undo' && act !== 'redo' && act !== 'all') {
            setCode(ed).then(function () { if (ed.view) setAct(ed, act, btn); });
            return;
        }
        if (act === 'vars') return setHintOpen(ed, btn);
        if (ed.view) {
            ed.view.focus();
            ed.cm[cmds[act]](ed.view);
            return;
        }
        ed.area.focus();
        if (act === 'all') ed.area.select();
        else if (act === 'undo' || act === 'redo') doc.execCommand(act);
    }

    // The palette of the page, made from the kit on the first call and wired once: typing filters, the keys choose and run, a click runs
    function getPalette() {
        var find;
        if (pal || !getKit() || !kit.palette) return pal;
        pal = kit.palette.cloneNode(true);
        pal.slVarRow = pal.querySelector('[data-sl-editor-cmd="var"]');
        pal.slVarRow.remove();
        doc.body.appendChild(pal);
        find = pal.querySelector('[data-sl-editor-find]');
        find.addEventListener('input', function () { setPaletteFilter(find.value); });
        pal.addEventListener('keydown', setPaletteKeys);
        pal.addEventListener('close', function () {
            var ed = pal.slEd;
            var act = pal.slAct;
            pal.slAct = '';
            if (!act || !ed || eds.get(ed.frame) !== ed) return;
            if (act === 'var') addVar(ed, pal.slVar);
            else setAct(ed, act, ed.frame.querySelector('[data-sl-editor-act="' + act + '"]'));
        });
        pal.addEventListener('click', function (ev) {
            var row = ev.target.closest ? ev.target.closest('[data-sl-editor-cmd]') : null;
            if (!row) return;
            ev.preventDefault();
            setPaletteRun(row);
        });
        return pal;
    }

    // Open the palette for one editor with only the commands and keys its engine serves, the search empty and the first command chosen
    function setPalette(ed) {
        var find;
        if (!getPalette()) return;
        pal.slEd = ed;
        pal.slAct = '';
        setPaletteVars(ed);
        pal.querySelector('[data-sl-editor-pal-sub]').textContent = ed.frame.getAttribute('data-sl-editor-name') || '';
        pal.querySelectorAll('[data-sl-editor-cmd]').forEach(function (row) { row.slOff = !checkNeed(ed.can, row); });
        pal.querySelectorAll('[data-sl-editor-pal-help] [data-sl-editor-need]').forEach(function (one) { one.hidden = !checkNeed(ed.can, one); });
        find = pal.querySelector('[data-sl-editor-find]');
        find.value = '';
        setPaletteFilter('');
        if (win.setWindowOpen) win.setWindowOpen(pal);
        else pal.showModal();
    }

    // Put the variables of the frame into their group of the palette, rebuilt only when a frame with other variables opens it
    function setPaletteVars(ed) {
        var key = ed.frame.getAttribute('data-sl-editor-vars') || '';
        var group = pal.querySelector('[data-sl-editor-pal-vars]');
        if (pal.slVars === key) return;
        pal.slVars = key;
        group.querySelectorAll('[data-sl-editor-cmd="var"]').forEach(function (row) { row.remove(); });
        (ed.vars || []).forEach(function (one, i) {
            var row = pal.slVarRow.cloneNode(true);
            row.id = 'sl-editor-cmd-var-' + i;
            row.setAttribute('data-sl-editor-var', one[0]);
            row.querySelector('span').textContent = one[0] + ' — ' + one[1];
            group.appendChild(row);
        });
    }

    // The places of the typed letters inside a name, in the order they were typed, or null where one of them is missing
    function getHits(name, want) {
        var hits = [];
        var at = 0;
        for (var i = 0; i < want.length; i++) {
            at = name.indexOf(want.charAt(i), at);
            if (at < 0) return null;
            hits.push(at);
            at++;
        }
        return hits;
    }

    // Write the name of a command with the matched letters in bold, from text nodes so nothing of the name is read as markup
    function setPaletteName(node, name, hits) {
        var run = '';
        var mark;
        node.textContent = '';
        for (var i = 0; i < name.length; i++) {
            if (hits.indexOf(i) < 0) {
                run += name.charAt(i);
                continue;
            }
            if (run) node.appendChild(doc.createTextNode(run));
            run = '';
            mark = doc.createElement('b');
            mark.textContent = name.charAt(i);
            node.appendChild(mark);
        }
        if (run) node.appendChild(doc.createTextNode(run));
    }

    // Keep the commands whose name holds the typed letters in their order, hide the groups left empty and the keys while a search runs
    function setPaletteFilter(term) {
        var want = term.trim().toLowerCase();
        var first = null;
        pal.querySelectorAll('[data-sl-editor-cmd]').forEach(function (row) {
            var name = row.querySelector('span');
            var hits;
            if (row.slName === undefined) row.slName = name.textContent;
            hits = getHits(row.slName.toLowerCase(), want);
            row.hidden = row.slOff || !hits;
            setPaletteName(name, row.slName, hits || []);
            if (!row.hidden && !first) first = row;
        });
        pal.querySelectorAll('[role="group"]').forEach(function (group) { group.hidden = !group.querySelector('[data-sl-editor-cmd]:not([hidden])'); });
        pal.querySelector('[data-sl-editor-pal-empty]').hidden = !!first;
        pal.querySelector('[data-sl-editor-pal-help]').hidden = want !== '';
        setPaletteActive(first);
    }

    // Choose one command; the focus stays in the search, which names the chosen one to a screen reader
    function setPaletteActive(row) {
        var find = pal.querySelector('[data-sl-editor-find]');
        pal.querySelectorAll('[data-sl-editor-cmd][aria-selected]').forEach(function (one) { one.removeAttribute('aria-selected'); });
        if (!row) {
            find.removeAttribute('aria-activedescendant');
            return;
        }
        row.setAttribute('aria-selected', 'true');
        find.setAttribute('aria-activedescendant', row.id);
        row.scrollIntoView({ block: 'nearest' });
    }

    // Step the chosen command up or down through the shown ones, round from the end to the start
    function setPaletteStep(step) {
        var rows = Array.prototype.filter.call(pal.querySelectorAll('[data-sl-editor-cmd]'), function (one) { return !one.hidden; });
        var now;
        if (!rows.length) return;
        now = rows.indexOf(pal.querySelector('[data-sl-editor-cmd][aria-selected]'));
        setPaletteActive(rows[(now + step + rows.length) % rows.length]);
    }

    // The keys of the palette: the arrows choose, Enter in the search runs, Escape closes at once over a typed search, Ctrl+K closes it again
    function setPaletteKeys(ev) {
        if (ev.isComposing) return;
        if (ev.key === 'ArrowDown' || ev.key === 'ArrowUp') {
            ev.preventDefault();
            setPaletteStep(ev.key === 'ArrowDown' ? 1 : -1);
            return;
        }
        if (ev.key === 'Enter' && ev.target.hasAttribute('data-sl-editor-find')) {
            ev.preventDefault();
            setPaletteRun(pal.querySelector('[data-sl-editor-cmd][aria-selected]'));
            return;
        }
        if (ev.key !== 'Escape' && !((ev.ctrlKey || ev.metaKey) && ev.code === 'KeyK')) return;
        ev.preventDefault();
        setPaletteShut();
    }

    // Name the command the palette runs once it has closed and given the focus back, a second press naming it again, and raise it in its group
    function setPaletteRun(row) {
        var ed = pal.slEd;
        var act;
        if (!row || row.hidden || !ed) return;
        act = row.getAttribute('data-sl-editor-cmd');
        pal.slVar = row.getAttribute('data-sl-editor-var') || '';
        pal.querySelectorAll('[data-recent]').forEach(function (one) { one.removeAttribute('data-recent'); });
        row.parentNode.insertBefore(row, row.parentNode.querySelector('[data-sl-editor-cmd]'));
        row.setAttribute('data-recent', kit.words.recent || '');
        pal.slAct = act;
        setPaletteShut();
    }

    // Close the palette through the canon, which plays the exit first
    function setPaletteShut() {
        if (win.setWindowClose) win.setWindowClose(pal);
        else pal.close();
    }

    // Cut a text into variables, words, runs of space and single signs, the units the comparison counts in
    function getTokens(text) {
        return text.match(/\[\w+\]|[\p{L}\p{N}_]+|\s+|[^\p{L}\p{N}_\s]/gu) || [];
    }

    // Cut a text into lines, each keeping its line end, the units a long text is aligned by first
    function getLines(text) {
        return text.match(/[^\n]*\n|[^\n]+$/g) || [];
    }

    // Align two lists through their longest common subsequence and add each unit to the parts as kept, gone or new
    function addSubsequence(out, was, now) {
        var cols = now.length + 1;
        var grid = new Uint16Array((was.length + 1) * cols);
        var i;
        var j;
        for (i = was.length - 1; i >= 0; i--) {
            for (j = now.length - 1; j >= 0; j--) {
                grid[i * cols + j] = was[i] === now[j] ? grid[(i + 1) * cols + j + 1] + 1 : Math.max(grid[(i + 1) * cols + j], grid[i * cols + j + 1]);
            }
        }
        i = 0;
        j = 0;
        while (i < was.length || j < now.length) {
            if (i < was.length && j < now.length && was[i] === now[j]) {
                out.push([0, was[i++]]);
                j++;
            } else if (j >= now.length || (i < was.length && grid[(i + 1) * cols + j] >= grid[i * cols + j + 1])) {
                out.push([-1, was[i++]]);
            } else {
                out.push([1, now[j++]]);
            }
        }
    }

    // Compare two lists of units: the same head and tail are kept as they stand, the middle is aligned while its table stays in the limit, else by lines
    function addParts(out, was, now, deep) {
        var head = 0;
        var tail = 0;
        var end;
        while (head < was.length && head < now.length && was[head] === now[head]) head++;
        while (tail < was.length - head && tail < now.length - head && was[was.length - 1 - tail] === now[now.length - 1 - tail]) tail++;
        if (head) out.push([0, was.slice(0, head).join('')]);
        end = now.slice(now.length - tail).join('');
        was = was.slice(head, was.length - tail);
        now = now.slice(head, now.length - tail);
        if ((was.length + 1) * (now.length + 1) <= cells) addSubsequence(out, was, now);
        else if (deep) addLines(out, was.join(''), now.join(''));
        else {
            if (was.length) out.push([-1, was.join('')]);
            if (now.length) out.push([1, now.join('')]);
        }
        if (end) out.push([0, end]);
    }

    // Compare a long text by lines and the lines that changed by their units, so the table never grows with the square of the whole text
    function addLines(out, was, now) {
        var rows = [];
        var gone = '';
        var came = '';
        var flush = function () {
            if (gone && came) addParts(out, getTokens(gone), getTokens(came), false);
            else if (gone) out.push([-1, gone]);
            else if (came) out.push([1, came]);
            gone = '';
            came = '';
        };
        addParts(rows, getLines(was), getLines(now), false);
        rows.forEach(function (one) {
            if (one[0] < 0) gone += one[1];
            else if (one[0] > 0) came += one[1];
            else {
                flush();
                out.push(one);
            }
        });
        flush();
    }

    // The loaded text against the present one: the parts in order, joined where they run on, and the count of the units gone and new
    function getDiff(was, now) {
        var parts = [];
        var out = [];
        var del = 0;
        var ins = 0;
        if (was !== now) addParts(parts, getTokens(was), getTokens(now), true);
        else if (was) parts.push([0, was]);
        parts.forEach(function (one) {
            var last = out[out.length - 1];
            if (one[0] < 0) del += getTokens(one[1]).length;
            if (one[0] > 0) ins += getTokens(one[1]).length;
            if (last && last[0] === one[0]) last[1] += one[1];
            else out.push([one[0], one[1]]);
        });
        return { parts: out, del: del, ins: ins };
    }

    // The comparison window of the page, made from the kit on the first call and wired once; "back" takes the loaded text once the window has closed
    function getDiffWindow() {
        if (dif || !getKit() || !kit.diff) return dif;
        dif = kit.diff.cloneNode(true);
        doc.body.appendChild(dif);
        dif.addEventListener('click', function (ev) {
            var btn = ev.target.closest ? ev.target.closest('[data-sl-editor-diff-view], [data-sl-editor-diff-back]') : null;
            if (!btn) return;
            ev.preventDefault();
            if (!btn.hasAttribute('data-sl-editor-diff-back')) return setDiffView(btn.getAttribute('data-sl-editor-diff-view'));
            dif.slBack = true;
            if (win.setWindowClose) win.setWindowClose(dif);
            else dif.close();
        });
        dif.addEventListener('close', function () {
            var ed = dif.slEd;
            var back = dif.slBack;
            dif.slBack = false;
            if (back && ed && eds.get(ed.frame) === ed) setText(ed, ed.orig, kit.words.restored);
        });
        return dif;
    }

    // Show the comparison in one text or in two columns, the choice kept for the next opening
    function setDiffView(view) {
        var side = view === 'side';
        dif.querySelectorAll('[data-sl-editor-diff-view]').forEach(function (btn) {
            btn.setAttribute('aria-pressed', String(btn.getAttribute('data-sl-editor-diff-view') === view));
        });
        dif.querySelector('[data-sl-editor-diff-all]').hidden = side;
        dif.querySelector('[data-sl-editor-diff-side]').hidden = !side;
    }

    // Write the parts into a box as text, the gone ones struck out and the new ones marked, leaving out the kind of part the box does not show
    function setDiffCode(node, parts, skip) {
        var out = doc.createDocumentFragment();
        parts.forEach(function (one) {
            var tag;
            if (one[0] === skip) return;
            if (!one[0]) return out.appendChild(doc.createTextNode(one[1]));
            tag = doc.createElement(one[0] < 0 ? 'del' : 'ins');
            tag.textContent = one[1];
            out.appendChild(tag);
        });
        node.replaceChildren(out);
    }

    // Open the comparison of the loaded text with the present one of an editor, computed only now; the window moves nothing in the form
    function setDiff(ed) {
        var now = getText(ed);
        var same = now === ed.orig;
        var diff;
        if (!getDiffWindow()) return;
        diff = getDiff(ed.orig, now);
        dif.slEd = ed;
        dif.slBack = false;
        dif.querySelector('[data-sl-editor-diff-sub]').textContent = ed.frame.getAttribute('data-sl-editor-name') || '';
        dif.querySelector('[data-sl-editor-diff-ins]').textContent = '+' + diff.ins;
        dif.querySelector('[data-sl-editor-diff-del]').textContent = '−' + diff.del;
        dif.querySelector('[data-sl-editor-diff-count]').hidden = same;
        dif.querySelector('[data-sl-editor-diff-back]').hidden = same;
        dif.querySelector('[data-sl-editor-diff-same]').hidden = !same;
        setDiffCode(dif.querySelector('[data-sl-editor-diff-all]'), diff.parts, null);
        setDiffCode(dif.querySelector('[data-sl-editor-diff-was]'), diff.parts, 1);
        setDiffCode(dif.querySelector('[data-sl-editor-diff-now]'), diff.parts, -1);
        if (win.setWindowOpen) win.setWindowOpen(dif);
        else dif.showModal();
    }

    // A value written into the page of the preview as an attribute, its markup characters taken as text
    function getAttrText(text) {
        return String(text).replace(/[&<>"]/g, function (one) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[one]; });
    }

    // The preview pane of a frame, made from the kit the first time it opens and standing in its card above the status line
    function getSheet(ed) {
        var pane;
        var open;
        if (ed.sheet) return ed.sheet;
        pane = kit.view.cloneNode(true);
        pane.id = 'sl-editor-view-' + (++uid);
        open = pane.querySelector('[data-sl-editor-view-open]');
        if (ed.preview.open) {
            open.href = ed.preview.open;
            open.hidden = false;
        }
        pane.querySelector('[data-sl-editor-view-frame]').addEventListener('load', function () { setSheetSeer(ed); });
        ed.sheet = pane;
        ed.card.insertBefore(pane, ed.card.querySelector('[data-sl-editor-status]'));
        ed.frame.querySelectorAll('[data-sl-editor-act="preview"]').forEach(function (btn) { btn.setAttribute('aria-controls', pane.id); });
        return pane;
    }

    // Show or hide the preview of a frame, under its text and beside it on the whole screen; it is rendered only while it is shown
    function setPreview(ed) {
        var on = !ed.frame.hasAttribute('data-view');
        var pane = getSheet(ed);
        ed.frame.toggleAttribute('data-view', on);
        pane.hidden = !on;
        ed.frame.querySelectorAll('[data-sl-editor-act="preview"]').forEach(function (btn) { btn.setAttribute('aria-pressed', String(on)); });
        if (ed.view) ed.view.requestMeasure();
        ed.sheetText = null;
        if (on) setSheet(ed);
    }

    // Render the present text into a shown preview: a template filled with its sample values at once, a text by the route of the site, an older answer dropped
    function setSheet(ed) {
        var text = getText(ed);
        var data = ed.preview;
        var body;
        var seq;
        if (!ed.sheet || ed.sheet.hidden || text === ed.sheetText) return;
        ed.sheetText = text;
        if (data.vals) {
            return setSheetPage(ed, text.replace(/\[\w+\]/g, function (key) { return Object.prototype.hasOwnProperty.call(data.vals, key) ? data.vals[key] : key; }));
        }
        seq = ++ed.sheetSeq;
        body = new FormData();
        body.append('text', text);
        body.append('mod', data.mod || '');
        body.append('store', data.store || '');
        body.append('token', data.token || '');
        fetch(data.url, { method: 'POST', body: body, credentials: 'same-origin' }).then(function (res) {
            if (!res.ok) throw new Error(String(res.status));
            return res.text();
        }).then(function (html) {
            if (seq === ed.sheetSeq && eds.get(ed.frame) === ed) setSheetPage(ed, html);
        }, function () {
            if (seq !== ed.sheetSeq) return;
            ed.sheetText = null;
            setNote(ed, kit.words.failed);
        });
    }

    // Write a page of the theme around a rendered body into the sandboxed frame, with the stylesheets and the colour mode of the page it stands on
    function setSheetPage(ed, body) {
        var root = doc.documentElement;
        var css = Array.prototype.map.call(doc.head.querySelectorAll('link[rel~="stylesheet"]'), function (one) {
            return '<link rel="stylesheet" href="' + getAttrText(one.href) + '">';
        }).join('');
        ed.sheet.querySelector('[data-sl-editor-view-frame]').srcdoc = '<!doctype html><html class="sl-editor-sheet" lang="' + getAttrText(root.lang || '') + '" data-theme="'
            + getAttrText(root.getAttribute('data-theme') || 'auto') + '"><head><meta charset="utf-8"><base href="' + getAttrText(doc.baseURI) + '">' + css
            + '</head><body>' + body + '</body></html>';
    }

    // Fit the frame of the preview to the height of what it shows, which the page may read since the sandbox runs no script of its own
    function setSheetFit(ed) {
        var page = ed.sheet ? ed.sheet.querySelector('[data-sl-editor-view-frame]').contentDocument : null;
        if (page && page.body) ed.sheet.style.setProperty('--sl-d-view-height', Math.ceil(page.body.getBoundingClientRect().height) + 'px');
    }

    // Fit the frame once its page has loaded and again whenever that page grows, as a lazy picture or a video does after the load
    function setSheetSeer(ed) {
        var page = ed.sheet ? ed.sheet.querySelector('[data-sl-editor-view-frame]').contentDocument : null;
        if (ed.sheetSeer) ed.sheetSeer.disconnect();
        ed.sheetSeer = null;
        setSheetFit(ed);
        if (!page || !page.body || !win.ResizeObserver) return;
        ed.sheetSeer = new win.ResizeObserver(function () { setSheetFit(ed); });
        ed.sheetSeer.observe(page.body);
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
                    if (upd.docChanged || upd.selectionSet) {
                        setStatus(ed);
                        setHintShut(ed);
                    }
                })
            ];
            ed.cm = cm;
            ed.wrap = new cm.Compartment();
            exts.push(ed.wrap.of(ed.nowrap ? [] : cm.EditorView.lineWrapping));
            if (list[1]) exts.push(list[1].language());
            if (ed.vars) exts.push(getVarMarks(cm, ed), cm.EditorState.languageData.of(getVarData(ed)));
            if (ed.can.lint) exts.push(cm.linter(getLintSource(ed), { delay: 250 }), cm.lintGutter());
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
        var code = frame.hasAttribute('data-sl-editor-code');
        var badge = frame.querySelector('[data-sl-editor-act="lint"]');
        var files = null;
        var vars = null;
        var view = null;
        try { files = JSON.parse(frame.getAttribute('data-sl-editor-files') || 'null'); } catch (err) { files = null; }
        try { vars = JSON.parse(frame.getAttribute('data-sl-editor-vars') || 'null'); } catch (err) { vars = null; }
        try { view = JSON.parse(frame.getAttribute('data-sl-editor-preview') || 'null'); } catch (err) { view = null; }
        if (!Array.isArray(vars) || !vars.length) vars = null;
        if (!view || typeof view !== 'object' || !(view.vals || view.url) || !kit.view) view = null;
        var can = {
            text: !code,
            code: code,
            cm: frame.hasAttribute('data-sl-editor-core'),
            markdown: !code && frame.getAttribute('data-sl-editor-lang') === 'markdown',
            files: !!files,
            vars: !!vars,
            lint: !!badge && frame.hasAttribute('data-sl-editor-lint'),
            preview: !!view
        };
        pill.querySelectorAll('[data-sl-editor-need]').forEach(function (one) { if (!checkNeed(can, one)) one.remove(); });
        Array.prototype.slice.call(pill.children).forEach(function (one) { if (!one.querySelector('button')) one.remove(); });
        var ed = {
            frame: frame,
            area: area,
            card: frame.querySelector('[data-sl-editor-card]'),
            pos: frame.querySelector('[data-sl-editor-pos]'),
            size: frame.querySelector('[data-sl-editor-size]'),
            note: frame.querySelector('[data-sl-editor-note]'),
            left: frame.querySelector('[data-sl-editor-left]'),
            used: frame.querySelector('[data-sl-editor-used]'),
            room: parseInt(frame.getAttribute('data-sl-editor-room') || '0', 10) || 0,
            vars: vars,
            hint: null,
            hintFrom: null,
            badge: badge,
            list: null,
            issues: [],
            can: can,
            orig: area.defaultValue,
            key: getDraftKey(area),
            view: null,
            load: null,
            nowrap: false,
            files: files,
            bound: false,
            port: null,
            hook: null,
            preview: view,
            sheet: null,
            sheetText: null,
            sheetSeq: 0,
            sheetSeer: null
        };
        eds.set(frame, ed);
        pill.setAttribute('aria-label', getPhrase(kit.words.tools, frame.getAttribute('data-sl-editor-name') || ''));
        frame.querySelector('[data-sl-editor-row]').appendChild(pill);
        if (pill.querySelector('[data-sl-editor-act="grip"]')) setGrip(pill, pill.querySelector('[data-sl-editor-act="grip"]'));
        ['input', 'select', 'keyup', 'click', 'focus'].forEach(function (type) {
            area.addEventListener(type, function (ev) {
                if (ev.type === 'input') setChange(ed);
                setStatus(ed);
                if (vars && !can.cm && ev.type !== 'select' && ev.type !== 'focus') setHintType(ed, ev);
            });
        });
        area.addEventListener('invalid', function (ev) { setInvalid(ed, ev); });
        area.addEventListener('keydown', function (ev) { setListEnter(ed, ev); });
        frame.addEventListener('keydown', function (ev) { setKeys(ed, ev); }, true);
        if (vars) {
            frame.addEventListener('keydown', function (ev) { setHintKeys(ed, ev); }, true);
            ed.card.addEventListener('focusout', function (ev) {
                var next = ev.relatedTarget;
                if (!next || !(ed.card.contains(next) || next.getAttribute('data-sl-editor-act') === 'vars')) setHintShut(ed);
            });
        }
        if (files) {
            ed.card.addEventListener('paste', function (ev) { setImages(ed, ev); }, true);
            ed.card.addEventListener('drop', function (ev) { setImages(ed, ev); }, true);
            ed.card.addEventListener('dragover', function (ev) { setImageDrag(ed, ev); }, true);
        }
        frame.addEventListener('keydown', function (ev) {
            if (ev.key !== 'Escape' || ev.defaultPrevented || !frame.hasAttribute('data-full') || checkCovered(frame)) return;
            ev.preventDefault();
            setFull(ed, false);
        });
        frame.setAttribute('data-ready', '');
        frame.toggleAttribute('data-dirty', area.value !== ed.orig);
        setStatus(ed);
        setRoom(ed, area.value);
        setVarCount(ed, area.value);
        setLint(ed);
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
        if (ed.sheetSeer) ed.sheetSeer.disconnect();
        if (frame.hasAttribute('data-full')) doc.documentElement.classList.remove('sl-is-locked');
        if (ed.bound && win.SlaedFileManager) win.SlaedFileManager.deleteUpload(ed.area.id);
        if (win.SlaedEmoji) win.SlaedEmoji.deletePanel(ed.area.id);
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
        setRoom(ed, getText(ed));
        setVarCount(ed, getText(ed));
        setLint(ed);
        setSheet(ed);
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
        },
        getEditor: function (id) {
            var ed = getEditor(id);
            return ed ? getPort(ed) : null;
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
