(function () {
    // Demo stand for the pickers of the header. On a real page a choice is a POST answered by PHP, which writes the
    // cookie or the session and re-renders the document; a static file has no server, so the submit is caught here
    // and the same state is written by hand. Three axes share one mechanism: theme mode, content editor, language
    var AXIS = {
        mode: {
            keep: 'sl-demo-mode',
            first: 'auto',
            name: { auto: 'Как в системе', light: 'Светлая', dark: 'Тёмная' },
            icon: { auto: 'bi-circle-half', light: 'bi-sun', dark: 'bi-moon' },
            next: { auto: 'light', light: 'dark', dark: 'auto' }
        },
        editor: {
            keep: 'sl-demo-editor',
            first: 'toastui',
            name: { toastui: 'TOAST UI Markdown 3', ckeditor: 'CKEditor 5', tinymce: 'TinyMCE 8 Community', plain: 'Plain Textarea' },
            icon: { toastui: 'bi-markdown', ckeditor: 'bi-type', tinymce: 'bi-fonts', plain: 'bi-textarea-t' },
            next: { toastui: 'ckeditor', ckeditor: 'tinymce', tinymce: 'plain', plain: 'toastui' }
        },
        lang: {
            keep: 'sl-demo-lang',
            first: 'ru',
            name: { de: 'Немецкий', en: 'Английский', fr: 'Французский', pl: 'Польский', ru: 'Русский', uk: 'Украинский' },
            icon: { de: 'bi-translate', en: 'bi-translate', fr: 'bi-translate', pl: 'bi-translate', ru: 'bi-translate', uk: 'bi-translate' },
            flag: { de: 'de', en: 'gb', fr: 'fr', pl: 'pl', ru: 'ru', uk: 'ua' },
            next: { de: 'en', en: 'fr', fr: 'pl', pl: 'ru', ru: 'uk', uk: 'de' }
        }
    };
    var FLAGS = '../templates/admin/images/flags/';

    // The value the stand remembers between pages, so a variant opens in the state the last one left
    function getPickNow(axis) {
        var val = null;
        try {
            val = window.localStorage.getItem(AXIS[axis].keep);
        } catch (err) {
            val = null;
        }
        return AXIS[axis].name[val] ? val : AXIS[axis].first;
    }

    // Paint one display element in the value of its axis: the icon glyph, the flag image, the caption or the title
    function setPickFace(node, axis, val) {
        var set = AXIS[axis];
        if (node.hasAttribute('data-sl-icon')) node.className = 'bi ' + set.icon[val];
        if (node.hasAttribute('data-sl-flag')) {
            node.setAttribute('src', FLAGS + set.flag[val] + '.svg');
            node.setAttribute('alt', set.name[val]);
        }
        if (node.hasAttribute('data-sl-label')) node.textContent = set.name[val];
        if (node.hasAttribute('data-sl-step')) node.value = set.next[val];
        if (node.hasAttribute('data-sl-title')) {
            node.setAttribute('title', set.name[val]);
            node.setAttribute('aria-label', set.name[val]);
        }
        if (node.hasAttribute('data-sl-title-next')) {
            node.setAttribute('title', set.name[set.next[val]]);
            node.setAttribute('aria-label', set.name[set.next[val]]);
        }
    }

    // Paint every control of one axis, and for the colour mode the document attribute the themes read
    function setPickShow(axis, val) {
        if (axis === 'mode') document.documentElement.setAttribute('data-theme', val);
        document.querySelectorAll('[data-sl-pick="' + axis + '"]').forEach(function (root) {
            root.setAttribute('data-sl-now', val);
            root.querySelectorAll('[data-sl-icon], [data-sl-flag], [data-sl-label], [data-sl-step], [data-sl-title], [data-sl-title-next]').forEach(function (node) {
                setPickFace(node, axis, val);
            });
            root.querySelectorAll('[data-sl-val]').forEach(function (node) {
                var seen = node.getAttribute('data-sl-val') === val;
                node.classList.toggle('sl-is-active', seen);
                if (node.tagName === 'BUTTON') node.setAttribute('aria-pressed', seen ? 'true' : 'false');
            });
            var drop = root.querySelector('select[data-sl-drop]');
            if (drop) drop.value = val;
        });
        document.querySelectorAll('[data-sl-label="' + axis + '"]').forEach(function (node) {
            node.textContent = AXIS[axis].name[val];
        });
    }

    // Every open fan goes down after a choice, the way it does on a page that reloads under it
    function setDialsDown() {
        document.querySelectorAll('.sl-dial.sl-open').forEach(function (node) {
            node.classList.remove('sl-open', 'sl-dial-point');
            node.style.left = '';
            node.style.top = '';
        });
    }

    // Store a choice and repaint; a window that carried it closes behind the choice, and so does an open fan
    function setPickTake(axis, val, from) {
        if (!AXIS[axis] || !AXIS[axis].name[val]) return;
        try {
            window.localStorage.setItem(AXIS[axis].keep, val);
        } catch (err) {
            void err;
        }
        setPickShow(axis, val);
        // a window holding one axis has said everything it had to say and closes behind the choice; a window
        // holding several is a settings sheet, and closing it after the first pick would hide the other two
        var box = from && from.closest ? from.closest('dialog[open]') : null;
        if (box && box.querySelectorAll('[data-sl-pick]').length === 1 && window.setWindowClose) window.setWindowClose(box);
        setDialsDown();
    }

    // A submit of a picker form never leaves the page here: the pressed button carries the value, and a lone
    // cycling button carries none, so the next value is read off the hidden field the server would have used
    function setPickSubmit(event) {
        var root = event.target && event.target.closest ? event.target.closest('[data-sl-pick]') : null;
        if (!root) return;
        event.preventDefault();
        var axis = root.getAttribute('data-sl-pick');
        var from = event.submitter;
        var val = from && from.getAttribute ? from.getAttribute('data-sl-val') : '';
        var step = root.querySelector('[data-sl-step]');
        if (!val && step) val = step.value;
        setPickTake(axis, val || '', from);
    }

    // A language is a plain link on the real page, and a select is a select: both are taken before they navigate
    function setPickClick(event) {
        var node = event.target && event.target.closest ? event.target.closest('[data-sl-val]') : null;
        if (!node || node.tagName !== 'A') return;
        var root = node.closest('[data-sl-pick]');
        if (!root) return;
        event.preventDefault();
        setPickTake(root.getAttribute('data-sl-pick'), node.getAttribute('data-sl-val'), node);
    }

    function setPickChange(event) {
        var node = event.target;
        if (!node || !node.hasAttribute || !node.hasAttribute('data-sl-drop')) return;
        var root = node.closest('[data-sl-pick]');
        if (!root) return;
        setPickTake(root.getAttribute('data-sl-pick'), node.value, node);
    }

    document.addEventListener('submit', setPickSubmit);
    document.addEventListener('click', setPickClick);
    document.addEventListener('change', setPickChange);
    Object.keys(AXIS).forEach(function (axis) { setPickShow(axis, getPickNow(axis)); });
})();
