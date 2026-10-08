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
    var enc = win.TextEncoder ? new win.TextEncoder() : null;
    var cmds = { undo: 'undo', redo: 'redo', all: 'selectAll', search: 'openSearchPanel', fold: 'foldAll', unfold: 'unfoldAll', comment: 'toggleComment' };

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
            palette: node.content.querySelector('[data-sl-editor-palette]')
        };
        return kit;
    }

    // Whether an engine serves a command or a key: every word of its need must be a power of the frame
    function checkNeed(can, node) {
        return (node.getAttribute('data-sl-editor-need') || '').split(' ').every(function (one) { return !one || can[one]; });
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

    // Mark an editor as changed against the text it was loaded with, and keep the draft of the tab after a pause
    function setChange(ed) {
        var text = getText(ed);
        if (ed.frame.hasAttribute('data-invalid')) setValid(ed);
        ed.frame.toggleAttribute('data-dirty', text !== ed.orig);
        setRoom(ed, text);
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
            ed.view.dispatch({ changes: { from: from, to: to, insert: text }, selection: { anchor: head, head: tail }, scrollIntoView: true });
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

    // The keys a frame answers before its engine: Ctrl+K opens the palette anywhere in the frame, Ctrl+B and Ctrl+I mark a Markdown text under the caret
    function setKeys(ed, ev) {
        var act = '';
        var text = ev.target === ed.area || (ed.view && ed.view.contentDOM.contains(ev.target));
        if (!(ev.ctrlKey || ev.metaKey) || ev.altKey || ev.shiftKey || ev.isComposing) return;
        if (ev.code === 'KeyK') act = 'palette';
        else if (text && ed.can.markdown) act = { KeyB: 'bold', KeyI: 'italic' }[ev.code] || '';
        if (!act) return;
        ev.preventDefault();
        ev.stopPropagation();
        setAct(ed, act, null);
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
        if (md && (act === 'bold' || act === 'italic')) return setInline(ed, act === 'bold' ? '**' : '*');
        if (md && act === 'code') return setCodeMark(ed);
        if (md && act === 'link') return setLink(ed);
        if (md && (act === 'list' || act === 'quote')) return setLines(ed, act === 'list' ? '- ' : '> ');
        if (!cmds[act]) return;
        if (!ed.view && ed.can.cm && act !== 'undo' && act !== 'redo' && act !== 'all') {
            setCode(ed).then(function () { if (ed.view) setAct(ed, act, btn); });
            return;
        }
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
        doc.body.appendChild(pal);
        find = pal.querySelector('[data-sl-editor-find]');
        find.addEventListener('input', function () { setPaletteFilter(find.value); });
        pal.addEventListener('keydown', setPaletteKeys);
        pal.addEventListener('close', function () {
            var ed = pal.slEd;
            var act = pal.slAct;
            pal.slAct = '';
            if (act && ed && eds.get(ed.frame) === ed) setAct(ed, act, ed.frame.querySelector('[data-sl-editor-act="' + act + '"]'));
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
        pal.querySelector('[data-sl-editor-pal-sub]').textContent = ed.frame.getAttribute('data-sl-editor-name') || '';
        pal.querySelectorAll('[data-sl-editor-cmd]').forEach(function (row) { row.slOff = !checkNeed(ed.can, row); });
        pal.querySelectorAll('[data-sl-editor-pal-help] [data-sl-editor-need]').forEach(function (one) { one.hidden = !checkNeed(ed.can, one); });
        find = pal.querySelector('[data-sl-editor-find]');
        find.value = '';
        setPaletteFilter('');
        if (win.setWindowOpen) win.setWindowOpen(pal);
        else pal.showModal();
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
        var code = frame.hasAttribute('data-sl-editor-code');
        var files = null;
        try { files = JSON.parse(frame.getAttribute('data-sl-editor-files') || 'null'); } catch (err) { files = null; }
        var can = {
            text: !code,
            code: code,
            cm: frame.hasAttribute('data-sl-editor-core'),
            markdown: !code && frame.getAttribute('data-sl-editor-lang') === 'markdown',
            files: !!files
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
            room: parseInt(frame.getAttribute('data-sl-editor-room') || '0', 10) || 0,
            can: can,
            orig: area.defaultValue,
            key: getDraftKey(area),
            view: null,
            load: null,
            nowrap: false,
            files: files,
            bound: false,
            port: null,
            hook: null
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
        area.addEventListener('keydown', function (ev) { setListEnter(ed, ev); });
        frame.addEventListener('keydown', function (ev) { setKeys(ed, ev); }, true);
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
