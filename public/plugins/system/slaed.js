(function () {
    // Window canon: the native dialog gives the focus trap, the top layer and the return of the focus; only what the platform does not give is written here
    var winstack = [];
    var winlayer = 10050;
    var windrag = null;
    var WINPICKS = 'a[href], button:not([disabled]), input:not([disabled]):not([type="hidden"]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])';
    // How much of a window has to stay on screen for a hand to reach its head again
    var WINHIGH = 42;
    var WINWIDE = 120;
    // A theme without sounds loads no sound.js, so its places of sound stay silent
    window.setSoundPlay = window.setSoundPlay || function () {};
    window.setSoundOn = window.setSoundOn || function () {};

    // The presentation is a boolean of the markup: a window is either modal or stands beside the page
    function isWindowModal(box) {
        return !box.hasAttribute('data-sl-window');
    }

    // The page under a modal window stops scrolling, and the width of the scrollbar is paid back so the content does not jump sideways
    function setPageLock(on) {
        var root = document.documentElement;
        if (on) {
            if (root.classList.contains('sl-is-locked')) return;
            root.style.paddingRight = (window.innerWidth - root.clientWidth) + 'px';
            root.classList.add('sl-is-locked');
            return;
        }
        root.classList.remove('sl-is-locked');
        root.style.paddingRight = '';
    }

    // The actions of the head are chrome and never the first stop: a window that opens on its own close button reads as a window asking to be shut
    function setFirstFocus(box) {
        var pick = box.querySelector('[data-sl-focus]');
        if (!pick) {
            Array.prototype.some.call(box.querySelectorAll(WINPICKS), function (one) {
                if (one.closest('.sl-modal-title')) return false;
                pick = one;
                return true;
            });
        }
        if (pick) pick.focus();
    }

    // Every size here is read off the layout, never off a client rect: the entry animation holds the window at scale(0.97), and a rect through it is short
    function setWindowPlace(box) {
        if (window.matchMedia('(max-width: 600px)').matches) {
            box.style.left = '';
            box.style.top = '';
            return;
        }
        if (box.getAttribute('data-sl-moved')) return;
        box.style.left = Math.max(12, Math.round((window.innerWidth - box.offsetWidth) / 2)) + 'px';
        box.style.top = '40px';
    }

    // The raised window becomes the top one for Escape as well, or the key would close what lies visually below
    function setWindowFront(box) {
        var at;
        if (!box || isWindowModal(box) || !box.open) return;
        at = winstack.indexOf(box);
        winlayer += 2;
        box.style.zIndex = String(winlayer);
        if (at > -1 && at !== winstack.length - 1) {
            winstack.splice(at, 1);
            winstack.push(box);
        }
    }

    // Calling the opener of a window that already stands open is a request to bring it back and put the keyboard in it, not a miss: a button has no right to stay silent
    function setWindowOpen(box) {
        if (!box) return;
        if (box.open) {
            setWindowFront(box);
            if (!box.contains(document.activeElement)) setFirstFocus(box);
            return;
        }
        box.backnode = document.activeElement;
        if (isWindowModal(box)) {
            box.showModal();
            setPageLock(true);
            winstack.push(box);
        } else {
            box.show();
            setWindowPlace(box);
            winstack.push(box);
            setWindowFront(box);
        }
        window.requestAnimationFrame(function () { setFirstFocus(box); });
    }

    // A navigation closes every dialog, so the window open when its form left opens again on the next load; an htmx form swaps in place and leaves no mark
    var winmark = 'slaed-window:reopen';

    function setWindowMark(box) {
        try {
            window.sessionStorage.setItem(winmark, box && box.id ? box.id : '');
        } catch (err) {
        }
    }

    function setWindowBack() {
        var id = '';
        try {
            id = window.sessionStorage.getItem(winmark) || '';
            window.sessionStorage.removeItem(winmark);
        } catch (err) {
            return;
        }
        if (id) setWindowOpen(document.getElementById(id));
    }

    document.addEventListener('submit', function (event) {
        var form = event.target;
        if (!form || !form.closest || form.hasAttribute('hx-post') || form.hasAttribute('hx-get')) return;
        var box = form.closest('dialog');
        if (box && box.open) setWindowMark(box);
    });

    // The exit plays before it is made real, or the window blinks; with motion switched off it leaves at once instead of sitting out a fallback timer
    function setWindowClose(box) {
        var shut = false;
        var runs;
        var end;
        if (!box || !box.open || box.classList.contains('sl-is-shut')) return;
        end = function () {
            if (shut) return;
            shut = true;
            box.classList.remove('sl-is-shut');
            box.close();
        };
        box.classList.add('sl-is-shut');
        runs = box.getAnimations ? box.getAnimations({ subtree: false }).filter(function (one) { return one.animationName === 'sl-modal-out'; }) : [];
        if (!runs.length) {
            end();
            return;
        }
        window.setTimeout(end, 200);
        Promise.allSettled(runs.map(function (one) { return one.finished; })).then(end);
    }

    // A window that has left gives the page back its scroll; the browser returns the focus by itself only for a modal one
    function setWindowRelease(box) {
        var at = winstack.indexOf(box);
        if (at > -1) winstack.splice(at, 1);
        box.classList.remove('sl-is-shut');
        if (!winstack.filter(isWindowModal).length) setPageLock(false);
        if (!isWindowModal(box) && box.backnode && box.backnode.isConnected) box.backnode.focus();
        box.backnode = null;
    }

    // The place the window stood in is remembered and given back, or a moved window collapses into a corner it never came from
    function setWindowExpand(box, button) {
        var full = !box.classList.contains('sl-is-full');
        box.classList.toggle('sl-is-full', full);
        if (!isWindowModal(box)) {
            if (full) {
                box.setAttribute('data-sl-left', box.style.left);
                box.setAttribute('data-sl-top', box.style.top);
                box.style.left = '24px';
                box.style.top = '24px';
            } else {
                box.style.left = box.getAttribute('data-sl-left') || '';
                box.style.top = box.getAttribute('data-sl-top') || '';
                setWindowPlace(box);
            }
        }
        if (button) setExpandButton(button, full);
    }

    // Name and draw an expand button for the state it leads to: a window and the full screen of an editor share it
    function setExpandButton(button, full) {
        var icon = button.querySelector('.bi');
        button.title = button.getAttribute(full ? 'data-restore' : 'data-expand') || button.title;
        button.setAttribute('aria-label', button.title);
        if (!icon) return;
        icon.classList.toggle('bi-arrows-angle-expand', !full);
        icon.classList.toggle('bi-arrows-angle-contract', full);
    }

    // A window does not let itself be dragged off the screen whole: a piece of its plate stays in reach, or the hand can never bring it back
    function setWindowBounds(box, left, top) {
        box.style.left = Math.round(Math.min(Math.max(left, WINWIDE - box.offsetWidth), window.innerWidth - WINWIDE)) + 'px';
        box.style.top = Math.round(Math.min(Math.max(top, 0), Math.max(0, window.innerHeight - WINHIGH))) + 'px';
    }

    // cancel and close arrive at the dialog itself and do not bubble: they can only be heard in the capture phase
    function setWindowKeys() {
        document.addEventListener('cancel', function (event) {
            var box = event.target.closest ? event.target.closest('dialog.sl-modal') : null;
            if (!box) return;
            event.preventDefault();
            if (!box.hasAttribute('data-sl-static')) setWindowClose(box);
        }, true);
        document.addEventListener('close', function (event) {
            var box = event.target.closest ? event.target.closest('dialog.sl-modal') : null;
            if (box) setWindowRelease(box);
        }, true);
        // A window beside the page is not closed by the browser on this key, and a fan standing open at the pointer is what the key reaches first
        document.addEventListener('keydown', function (event) {
            var box = winstack[winstack.length - 1];
            if (event.key !== 'Escape' || event.defaultPrevented || !box || isWindowModal(box)) return;
            if (box.hasAttribute('data-sl-static') || document.querySelector('.sl-dial.sl-open')) return;
            event.preventDefault();
            setWindowClose(box);
        });
        // Any approach to a window beside the page raises it; the capture phase settles the order before the drag takes over
        document.addEventListener('pointerdown', function (event) {
            var box = event.target.closest ? event.target.closest('dialog.sl-modal[data-sl-window]') : null;
            if (box) setWindowFront(box);
        }, true);
        document.addEventListener('focusin', function (event) {
            var box = event.target.closest ? event.target.closest('dialog.sl-modal[data-sl-window]') : null;
            if (box) setWindowFront(box);
        });
        // The drag starts from the layout place: a grip taken while the window still animates in would pin it where the transform draws it
        document.addEventListener('pointerdown', function (event) {
            var box = event.target.closest ? event.target.closest('dialog.sl-modal[data-sl-window]') : null;
            var head = event.target.closest ? event.target.closest('.sl-modal-title') : null;
            var act = event.target.closest ? event.target.closest('button, a, input, select, textarea') : null;
            var left;
            var top;
            if (!box || !head || act || event.button !== 0) return;
            if (box.classList.contains('sl-is-full')) return;
            left = box.offsetLeft;
            top = box.offsetTop;
            box.style.left = left + 'px';
            box.style.top = top + 'px';
            box.setAttribute('data-sl-moved', '1');
            box.setAttribute('data-moving', '');
            windrag = { box: box, left: left, top: top, x: event.clientX, y: event.clientY };
            event.preventDefault();
        });
        document.addEventListener('pointermove', function (event) {
            if (!windrag) return;
            setWindowBounds(windrag.box, windrag.left + event.clientX - windrag.x, windrag.top + event.clientY - windrag.y);
        });
        ['pointerup', 'pointercancel'].forEach(function (type) {
            document.addEventListener(type, function () {
                if (windrag) windrag.box.removeAttribute('data-moving');
                windrag = null;
            });
        });
        // A screen that shrank must not hide a window: on a phone the coordinates are dropped, on a desktop the window is pulled back into sight
        window.addEventListener('resize', function () {
            winstack.forEach(function (box) {
                if (isWindowModal(box) || box.classList.contains('sl-is-full')) return;
                if (window.matchMedia('(max-width: 600px)').matches) {
                    box.style.left = '';
                    box.style.top = '';
                    return;
                }
                setWindowBounds(box, parseFloat(box.style.left) || box.getBoundingClientRect().left, parseFloat(box.style.top) || box.getBoundingClientRect().top);
            });
        });
    }

    window.setWindowOpen = setWindowOpen;
    window.setWindowClose = setWindowClose;
    window.setWindowFront = setWindowFront;
    window.setWindowExpand = setWindowExpand;
    window.setExpandButton = setExpandButton;

    // Lightbox: an image link opens the gallery window the page carries, the same one the editor and the file browser open with their own data
    function setLightbox() {
        var root = document.querySelector('dialog[data-sl-shot="view"]');
        var image = root ? root.querySelector('[data-sl-shot-img]') : null;
        var label = root ? root.querySelector('[data-sl-shot-name]') : null;
        if (!root || !image || !label) return;
        root.addEventListener('close', function () {
            image.removeAttribute('src');
        });
        document.addEventListener('click', function (event) {
            var trigger = event.target.closest('a.sl-attach, a[data-sl-shot-open]');
            if (!trigger) return;
            var href = trigger.getAttribute('href') || '';
            if (!/\.(?:avif|gif|jpe?g|png|webp|svg)(?:[?#].*)?$/i.test(href)) return;
            event.preventDefault();
            image.src = href;
            image.alt = trigger.getAttribute('title') || '';
            label.textContent = trigger.getAttribute('title') || href.split('/').pop();
            setWindowOpen(root);
        });
    }

    function getTableNumber(text) {
        var item = (text || '').replace(/<[^>]*>/g, '').replace(/\s+/g, '').replace(/,/g, '.');
        var val = item.match(/[-+]?\d+(?:\.\d+)?/);
        return val ? parseFloat(val[0]) : 0;
    }

    function getTableDate(text) {
        var item = (text || '').trim();
        var val = item.match(/^(\d{2})\.(\d{2})\.(\d{4})(?:\s+(\d{2}):(\d{2})(?::(\d{2}))?)?$/);
        if (!val) return 0;
        var sec = val[6] ? parseInt(val[6], 10) : 0;
        return new Date(
            parseInt(val[3], 10),
            parseInt(val[2], 10) - 1,
            parseInt(val[1], 10),
            parseInt(val[4] || '0', 10),
            parseInt(val[5] || '0', 10),
            sec
        ).getTime();
    }

    function setTableSortState(table) {
        if (!table || !table.tHead) return;
        var head = table.tHead.rows;
        for (var i = 0; i < head.length; i++) {
            for (var j = 0; j < head[i].cells.length; j++) {
                var cell = head[i].cells[j];
                cell.classList.remove('sl-sort', 'sl-sort-asc', 'sl-sort-desc');
                if (cell.getAttribute('data-sort-method') === 'none') continue;
                cell.classList.add('sl-sort');
                if (cell.getAttribute('aria-sort') === 'ascending') {
                    cell.classList.remove('sl-sort');
                    cell.classList.add('sl-sort-asc');
                } else if (cell.getAttribute('aria-sort') === 'descending') {
                    cell.classList.remove('sl-sort');
                    cell.classList.add('sl-sort-desc');
                }
            }
        }
    }

    function setTableSort(node) {
        if (typeof window.Tablesort === 'undefined') return;
        if (typeof window.Tablesort.extend === 'function' && !window.Tablesort.__slaedExtensionsReady) {
            window.Tablesort.extend('slaedNumber', function (item) {
                return /^[-+]?[\d\s.,]+(?:\s*[%A-Za-zА-Яа-я]+)?$/.test(item.trim()) && /\d/.test(item);
            }, function (a, b) {
                return getTableNumber(b) - getTableNumber(a);
            });

            window.Tablesort.extend('slaedDate', function (item) {
                return /^\d{2}\.\d{2}\.\d{4}(?:\s+\d{2}:\d{2}(?::\d{2})?)?$/.test(item.trim());
            }, function (a, b) {
                return getTableDate(b) - getTableDate(a);
            });

            window.Tablesort.__slaedExtensionsReady = true;
        }
        var list = [];
        var root = node && node.nodeType ? node : document;
        var tables = root.querySelectorAll ? root.querySelectorAll('[data-sl-table-sort]') : [];
        if (root.nodeType === 1 && root.hasAttribute && root.hasAttribute('data-sl-table-sort')) {
            list.push(root);
        }
        for (var i = 0; i < tables.length; i++) {
            list.push(tables[i]);
        }
        for (var k = 0; k < list.length; k++) {
            var table = list[k];
            if (!table.tHead || !table.tBodies.length) continue;
            if (table.getAttribute('data-sort-ready') !== '1') {
                new window.Tablesort(table);
                table.addEventListener('afterSort', function (event) {
                    setTableSortState(event.currentTarget);
                    setTableSortSaid(event.currentTarget);
                });
                table.setAttribute('data-sort-ready', '1');
                setTableSortKept(table);
            }
            setTableSortState(table);
        }
    }

    // Э19 A table naming its key gets back the sort its viewer chose last, quietly, as nothing was asked
    function setTableSortKept(table) {
        var key = table.getAttribute('data-sl-sort-keep');
        var kept = null;
        if (!key) return;
        try {
            kept = JSON.parse(getToggleState('sort-' + key, 'site') || 'null');
        } catch (err) {
            kept = null;
        }
        var cell = kept && table.tHead.rows[0] ? table.tHead.rows[0].cells[kept.at] : null;
        if (!cell || cell.getAttribute('data-sort-method') === 'none') return;
        table.slSortQuiet = true;
        cell.click();
        if (cell.getAttribute('aria-sort') !== kept.dir) cell.click();
        table.slSortQuiet = false;
    }

    // Э19 A sort the viewer made is kept for the table's key and said on the status line of the page
    function setTableSortSaid(table) {
        var key = table.getAttribute('data-sl-sort-keep');
        var cell = table.tHead ? table.tHead.querySelector('th[aria-sort]') : null;
        var said = document.querySelector('[data-sl-said]');
        if (!key || !cell || table.slSortQuiet) return;
        var dir = cell.getAttribute('aria-sort');
        setToggleState('sort-' + key, 'site', JSON.stringify({ at: cell.cellIndex, dir: dir }));
        if (!said) return;
        said.textContent = (table.getAttribute('data-sl-sort-said') || '') + ': ' + cell.textContent.trim() + ', '
            + (table.getAttribute(dir === 'ascending' ? 'data-sl-sort-asc' : 'data-sl-sort-desc') || '');
    }

    // A per-viewer choice kept for the site, for one address with scope "path", or never with scope "none"; refused storage keeps the defaults
    function getStorageKey(name, scope) {
        if (scope === 'none') return '';
        return scope === 'path' ? 'slaed-toggle:' + window.location.pathname + window.location.search + ':' + name : 'slaed-toggle:' + name;
    }

    function getToggleState(name, scope) {
        var key = getStorageKey(name, scope);
        if (!key) return null;
        try {
            return window.localStorage.getItem(key);
        } catch (err) {
            return null;
        }
    }

    function setToggleState(name, scope, value) {
        var key = getStorageKey(name, scope);
        if (!key) return;
        try {
            window.localStorage.setItem(key, value);
        } catch (err) {
        }
    }
    window.getToggleState = getToggleState;
    window.setToggleState = setToggleState;

    function getToggleControls(id) {
        var controls = document.querySelectorAll('[data-sl-toggle-control]');
        var matches = [];
        for (var i = 0; i < controls.length; i++) {
            if (controls[i].getAttribute('data-sl-toggle-control') === id) matches.push(controls[i]);
        }
        return matches;
    }

    function setToggleControls(id, isOpen) {
        var controls = getToggleControls(id);
        for (var i = 0; i < controls.length; i++) {
            controls[i].setAttribute('aria-expanded', isOpen ? 'true' : 'false');
            controls[i].classList.toggle('sl-is-open', isOpen);
            controls[i].classList.toggle('sl-is-closed', !isOpen);
            if (controls[i].type === 'checkbox') controls[i].checked = isOpen;
        }
    }

    // One source of truth for the display a toggled block opens with: explicit data-sl-toggle-display wins, grid containers open as grid, everything else as block
    function getToggleDisplay(element) {
        return element.getAttribute('data-sl-toggle-display') || ((element.classList.contains('sl-div-item') || element.classList.contains('sl-div-grid')) ? 'grid' : 'block');
    }

    // Float panels bypass the legacy display/slide/puff path: their visibility lives in CSS and placement in placeFloat, so toggling them never jumps
    function setToggleBlockState(element, id, isOpen, effect, duration) {
        element.classList.toggle('sl-is-open', isOpen);
        element.classList.toggle('sl-is-closed', !isOpen);
        setToggleControls(id, isOpen);
        if (element.classList.contains('sl-float-panel')) {
            var floatHost = element.closest('.sl-float');
            if (floatHost) {
                if (isOpen) placeFloat(floatHost); else clearFloatState(floatHost);
            }
            return;
        }
        if (effect === 'slide') {
            setSlideMotion(element, isOpen, duration || 400);
            return;
        }
        if (effect === 'puff') {
            setFadeScale(element, isOpen, duration || 400);
            return;
        }
        element.hidden = !isOpen;
        element.style.display = isOpen ? getToggleDisplay(element) : 'none';
    }

    // Blocks that share data-sl-toggle-group open one at a time: the block about to open closes the others with the motion of their own control
    function setToggleGroup(element) {
        var group = element.getAttribute('data-sl-toggle-group');
        if (!group) return;
        var list = document.querySelectorAll('[data-sl-toggle-group].sl-is-open');
        for (var i = 0; i < list.length; i++) {
            if (list[i] === element || list[i].getAttribute('data-sl-toggle-group') !== group || !list[i].id) continue;
            var control = getToggleControls(list[i].id)[0];
            var effect = control ? control.getAttribute('data-sl-toggle-effect') : null;
            var duration = parseInt((control && control.getAttribute('data-sl-toggle-duration')) || '400', 10);
            setToggleBlock(list[i].id, list[i].getAttribute('data-sl-toggle-scope') || '', false, effect, duration);
        }
    }

    // Whether a block stands open now: a float panel by its host, which hover and outside clicks drive past the toggle, any other block by its display
    function isToggleOpen(element) {
        if (element.classList.contains('sl-float-panel')) {
            var host = element.closest('.sl-float');
            return !!(host && host.classList.contains('sl-is-open'));
        }
        return !(window.getComputedStyle(element).display === 'none' || element.hidden);
    }

    function setToggleBlock(id, scope, isOpen, effect, duration) {
        var element = document.getElementById(id);
        if (!element) return;
        var nextOpen = typeof isOpen === 'boolean' ? isOpen : !isToggleOpen(element);
        if (nextOpen) setToggleGroup(element);
        setToggleBlockState(element, id, nextOpen, effect, duration);
        setToggleState(id, scope, nextOpen ? '1' : '0');
        if (nextOpen) {
            var items = getToggleControls(id);
            for (var num = 0; num < items.length; num++) items[num].dispatchEvent(new Event('sl-toggle-open'));
        }
    }

    function setToggleControl(control) {
        if (control.getAttribute('data-sl-toggle-ready') === '1') return;
        var id = control.getAttribute('data-sl-toggle-control');
        if (!id) return;
        var scope = control.getAttribute('data-sl-toggle-scope') || '';
        var isCheckbox = control.type === 'checkbox';
        var isNative = isCheckbox || control.tagName === 'BUTTON';
        control.setAttribute('data-sl-toggle-ready', '1');
        control.setAttribute('aria-controls', id);
        if (!isNative) {
            control.setAttribute('role', 'button');
            control.setAttribute('tabindex', '0');
        }
        var eventName = isCheckbox ? 'change' : 'click';
        control.addEventListener(eventName, function (event) {
            if (!isCheckbox) event.preventDefault();
            var effect = control.getAttribute('data-sl-toggle-effect');
            var duration = parseInt(control.getAttribute('data-sl-toggle-duration') || '400', 10);
            setToggleBlock(id, scope, isCheckbox ? control.checked : undefined, effect, duration);
        });
        control.addEventListener('keydown', function (event) {
            if (isNative) return;
            if (event.key !== 'Enter' && event.key !== ' ') return;
            event.preventDefault();
            var effect = control.getAttribute('data-sl-toggle-effect');
            var duration = parseInt(control.getAttribute('data-sl-toggle-duration') || '400', 10);
            setToggleBlock(id, scope, undefined, effect, duration);
        });
    }

    // Slide a block open or shut along its height, or its width on the axis "x"; a still page arrives at once, and the run is returned
    function setSlideMotion(element, show, duration, axis) {
        var prop = axis === 'x' ? 'width' : 'height';
        var anims = element.getAnimations ? element.getAnimations() : [];
        var from = {};
        var to = {};
        var run;
        for (var i = 0; i < anims.length; i++) anims[i].cancel();
        if (show) {
            element.hidden = false;
            element.style.display = getToggleDisplay(element);
            element.style[prop] = '0px';
            element.style.overflow = 'hidden';
            element.offsetHeight;
        } else {
            element.style.overflow = 'hidden';
            element.style[prop] = (axis === 'x' ? element.offsetWidth : element.offsetHeight) + 'px';
        }
        from[prop] = (show ? 0 : (axis === 'x' ? element.offsetWidth : element.offsetHeight)) + 'px';
        to[prop] = (show ? (axis === 'x' ? element.scrollWidth : element.scrollHeight) : 0) + 'px';
        run = element.animate([from, to], {
            duration: isMotionStill() ? 0 : duration,
            easing: 'linear'
        });
        run.onfinish = function () {
            element.style.overflow = '';
            element.style[prop] = '';
            if (!show) {
                element.style.display = 'none';
                element.hidden = true;
            }
        };
        return run;
    }
    window.setSlideMotion = setSlideMotion;

    function setFadeScale(element, show, duration) {
        if (show) {
            element.hidden = false;
            element.style.display = getToggleDisplay(element);
        }
        element.animate([
            { opacity: show ? 0 : 1, transform: show ? 'scale(0.96)' : 'scale(1)' },
            { opacity: show ? 1 : 0, transform: show ? 'scale(1)' : 'scale(0.96)' }
        ], {
            duration: isMotionStill() ? 0 : duration,
            easing: 'ease'
        }).onfinish = function () {
            element.style.opacity = '';
            element.style.transform = '';
            if (!show) {
                element.style.display = 'none';
                element.hidden = true;
            }
        };
    }

    function getJsonData(url) {
        return window.fetch(url, {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        }).then(function (response) {
            if (!response.ok) throw new Error('Request failed');
            return response.json();
        });
    }

    function setInputValueByClass(className, value) {
        var field = document.querySelector('input.' + className);
        if (field) field.value = value;
    }

    function setImageReplace() {
        var selects = document.querySelectorAll('[data-sl-image-replace]');
        selects.forEach(function (select) {
            var picture = document.getElementById(select.getAttribute('data-sl-image-replace') || '');
            if (!picture) return;
            select.addEventListener('change', function () {
                picture.setAttribute('src', this.value || '');
            });
        });
    }

    function setTableCheckAll() {
        document.addEventListener('click', function (event) {
            var cell = event.target.closest('th[data-sl-check-all]');
            if (!cell || event.target.matches('input[type="checkbox"]')) return;
            var checkbox = cell.querySelector('input[type="checkbox"]');
            if (checkbox) checkbox.click();
        });
        document.addEventListener('change', function (event) {
            var master = event.target;
            if (!master || master.type !== 'checkbox' || !master.closest('[data-sl-check-all]')) return;
            var scope = master.closest('form') || document;
            var items = scope.querySelectorAll('[data-sl-check-item]');
            for (var i = 0; i < items.length; i++) {
                if (items[i] !== master && !items[i].disabled) items[i].checked = !!master.checked;
            }
        });
    }

    // A block takes its default only once, marked as its control is; later runs after a swap keep the state it has and only tell its controls
    function setToggleBlocks() {
        var controls = document.querySelectorAll('[data-sl-toggle-control]');
        for (var i = 0; i < controls.length; i++) setToggleControl(controls[i]);

        var blocks = document.querySelectorAll('[data-sl-toggle]');
        for (var j = 0; j < blocks.length; j++) {
            var id = blocks[j].getAttribute('data-sl-toggle');
            var scope = blocks[j].getAttribute('data-sl-toggle-scope') || '';
            if (!id) continue;
            var seen = blocks[j].getAttribute('data-sl-toggle-ready') === '1';
            blocks[j].setAttribute('data-sl-toggle-ready', '1');
            var state = getToggleState(id, scope);
            var isOpen = state !== '0';
            if (state === null) {
                var defaultState = blocks[j].getAttribute('data-sl-toggle-default');
                if (seen) {
                    isOpen = isToggleOpen(blocks[j]);
                } else if (defaultState === 'closed') {
                    isOpen = false;
                } else if (defaultState === 'open') {
                    isOpen = true;
                }
                var blockControls = getToggleControls(id);
                for (var k = 0; k < blockControls.length; k++) {
                    if (blockControls[k].type === 'checkbox') {
                        isOpen = blockControls[k].checked;
                        break;
                    }
                }
            }
            setToggleBlockState(blocks[j], id, isOpen);
        }
    }

    // Editor assets: a page load carries the engines as plain tags; a fragment over htmx names them to this loader, which adds only what the page lacks
    var edwait = null;
    var edseen = {};
    var edowns = [];

    function getAssetUrl(src) {
        return new URL(src, document.baseURI).href;
    }

    function getEditorScript(src) {
        var url = getAssetUrl(src);
        if (edseen[url]) return edseen[url];
        if (Array.prototype.some.call(document.scripts, function (one) { return one.src === url; })) {
            edseen[url] = Promise.resolve();
            return edseen[url];
        }
        edseen[url] = new Promise(function (done) {
            var tag = document.createElement('script');
            tag.src = url;
            tag.async = false;
            tag.onload = done;
            tag.onerror = done;
            document.head.appendChild(tag);
        });
        return edseen[url];
    }

    function setEditorStyle(href) {
        var url = getAssetUrl(href);
        var have = Array.prototype.some.call(document.querySelectorAll('link[rel="stylesheet"]'), function (one) { return one.href === url; });
        if (have) return;
        var tag = document.createElement('link');
        tag.rel = 'stylesheet';
        tag.href = url;
        document.head.appendChild(tag);
    }

    window.SlaedEditors = {
        load: function (css, js) {
            css.forEach(setEditorStyle);
            var wait = Promise.all(js.map(getEditorScript));
            var mine = edwait ? Promise.all([edwait, wait]) : wait;
            edwait = mine;
            mine.then(function () { if (edwait === mine) edwait = null; });
        },
        ready: function (run) {
            if (edwait) edwait.then(run);
            else run();
        },
        own: function (node, kill) {
            edowns.push({ node: node, kill: kill });
        },
        drop: function (root) {
            edowns = edowns.filter(function (one) {
                if (one.node.isConnected && !root.contains(one.node)) return true;
                try { one.kill(); } catch (err) { return false; }
                return false;
            });
        }
    };

    // A name is text inside the bold markers, so a character of Markdown in it is escaped rather than read
    function getEditorInsertText(command, value, title) {
        var src;
        if (command === 'name') return '**' + value.replace(/[\\`*_[\]<>~=|]/g, '\\$&') + '**, ';
        if (command === 'attach') return '[attach=' + value + ' align=left title=title width=500 height=500 rel=rel]\n';
        if (command !== 'img') return value;
        src = value.replace(/[ ()<>]/g, function (one) { return '%' + one.charCodeAt(0).toString(16).toUpperCase(); });
        return '![' + (title || 'image').replace(/[[\]\\]/g, '') + '](' + src + ')\n';
    }

    function syncEditorValue(id, editor) {
        var field = document.getElementById(id);
        if (!field || !editor || typeof editor.getMarkdown !== 'function') return;
        field.value = editor.getMarkdown();
        field.dispatchEvent(new Event('input', { bubbles: true }));
        field.dispatchEvent(new Event('change', { bubbles: true }));
    }

    function insertTextareaText(field, text) {
        var start = typeof field.selectionStart === 'number' ? field.selectionStart : field.value.length;
        var end = typeof field.selectionEnd === 'number' ? field.selectionEnd : start;
        field.focus();
        if (typeof field.setRangeText === 'function') {
            field.setRangeText(text, start, end, 'end');
        } else {
            field.value = field.value.slice(0, start) + text + field.value.slice(end);
            field.selectionStart = field.selectionEnd = start + text.length;
        }
        field.dispatchEvent(new Event('input', { bubbles: true }));
        field.dispatchEvent(new Event('change', { bubbles: true }));
    }

    function insertEditorContent(id, command, value, title) {
        var api = window.SlaedToastUi;
        var editor = api && typeof api.getEditor === 'function' ? api.getEditor(id) : null;
        var text = getEditorInsertText(command, value, title);
        var shell = !editor && window.SlaedEditor ? window.SlaedEditor.getEditor(id) : null;
        if (shell) {
            shell.focus();
            if (command === 'img') shell.exec('addImage', { imageUrl: value, altText: title || 'image' });
            else shell.insertText(text);
            return true;
        }
        if (editor) {
            editor.focus();
            if (command === 'img' && typeof editor.exec === 'function') {
                editor.exec('addImage', { imageUrl: value, altText: title || 'image' });
            } else if (typeof editor.insertText === 'function') {
                editor.insertText(text);
            } else if (api && typeof api.insertText === 'function') {
                api.insertText(id, text);
            }
            syncEditorValue(id, editor);
            return true;
        }
        var field = document.getElementById(id);
        if (!field || field.tagName !== 'TEXTAREA') return false;
        insertTextareaText(field, text);
        return true;
    }

    function setEditorInsertHandler() {
        if (document.documentElement.getAttribute('data-sl-editor-insert-ready') === '1') return;
        document.documentElement.setAttribute('data-sl-editor-insert-ready', '1');
        document.addEventListener('click', function (event) {
            var control = event.target.closest('[data-sl-editor-insert]');
            if (!control) return;
            var command = control.getAttribute('data-sl-editor-insert') || '';
            var id = control.getAttribute('data-sl-editor-id') || '1';
            var value = control.getAttribute('data-sl-editor-value') || '';
            var title = control.getAttribute('data-sl-editor-title') || control.getAttribute('title') || '';
            if (!command || !id) return;
            event.preventDefault();
            insertEditorContent(id, command, value, title);
        });
    }

    // The text comes from the caller and never from this file, because every visible word of the project lives in a language constant
    window.setConfirmTask = function (text, run) {
        var dlg = document.getElementById('sl-confirm');
        window.setSoundPlay('warn');
        if (!dlg) {
            if (window.confirm(text)) run();
            return;
        }
        dlg.querySelector('[data-sl-confirm-text]').textContent = text;
        dlg.slask = null;
        dlg.slrun = run;
        setWindowOpen(dlg);
    };

    // A fan says on its toggle whether it stands open, so a reader hears the state the eye sees; only an open fan lets Tab into its items
    function setDialState(dial, open) {
        var btn = dial.querySelector('.sl-dial-toggle');
        dial.classList.toggle('sl-open', open);
        if (btn) btn.setAttribute('aria-expanded', open ? 'true' : 'false');
    }

    // A fan opened at the pointer gives its placement back when it goes down, because it belongs to the corner of its own object and stands there again next time
    function deleteDialPoint(dial) {
        setDialState(dial, false);
        dial.classList.remove('sl-dial-point');
        dial.style.left = '';
        dial.style.top = '';
    }

    // Every open fan of the page goes down at once, and one named is left standing
    function setDialsOff(keep) {
        document.querySelectorAll('.sl-dial.sl-open').forEach(function (dial) {
            if (dial !== keep) deleteDialPoint(dial);
        });
    }

    // The fan of an object is its context menu; a box holding several fans is the list around them, and there the menu belongs to the browser
    function getDialOwn(node) {
        var box = node && node.closest ? node.closest('.sl-dial') : null;
        var list;
        if (box) return box;
        while (node && node !== document.body) {
            list = node.querySelectorAll('.sl-dial');
            if (list.length > 1) return null;
            if (list.length === 1) return list[0];
            node = node.parentElement;
        }
        return null;
    }

    // The fan opens where the pointer stands and stays inside the box it is placed in, because the work area scrolls and a fan reaching past its edge would be cut away
    function setDialPoint(dial, event) {
        var host = dial.offsetParent || document.body;
        var box = host.getBoundingClientRect();
        var item = dial.querySelector('.sl-dial-item');
        var seen = dial.querySelectorAll('.sl-dial-item').length;
        var size = parseFloat(window.getComputedStyle(dial).getPropertyValue('--sl-size-chip')) || 32;
        var wide = (seen + 1) * (size + 6) + 12;
        var left = event.clientX - box.left;
        var top = event.clientY - box.top;
        setDialsOff(dial);
        dial.classList.add('sl-dial-point');
        setDialState(dial, true);
        // Which way the fan grows is a decision of the theme: an absolute item opens to the left of the toggle, an item in the flow follows it to the right
        if (item && window.getComputedStyle(item).position === 'absolute') left = Math.max(left, wide);
        else left = Math.min(left, Math.max(0, host.clientWidth - wide));
        dial.style.left = left + 'px';
        dial.style.top = top + 'px';
    }

    // An element that issues its own htmx request instead of following its address
    function isHtmxOwn(node) {
        return ['hx-get', 'hx-post', 'hx-put', 'hx-patch', 'hx-delete'].some(function (name) { return node.hasAttribute(name); });
    }

    // links carrying data-sl-confirm (plain text, escaped by the template) must pass a confirm dialog first
    function setDialToggle() {
        // The same question for an htmx element goes through the hook htmx opens before every request: the request waits for the answer, is issued on yes and never leaves on no
        document.addEventListener('htmx:confirm', function (event) {
            var own = event.detail && event.detail.elt;
            if (!own || !own.closest) return;
            var ask = own.closest('[data-sl-confirm]');
            if (!ask || own.hasAttribute('hx-confirm')) return;
            event.preventDefault();
            window.setConfirmTask(ask.getAttribute('data-sl-confirm'), function () { event.detail.issueRequest(true); });
        });
        document.addEventListener('contextmenu', function (event) {
            var dial = getDialOwn(event.target);
            if (!dial || !dial.querySelector('.sl-dial-item')) return;
            event.preventDefault();
            setDialPoint(dial, event);
        });
        // Escape folds every fan, and a focus inside one goes back to its toggle instead of being dropped on the page
        document.addEventListener('keydown', function (event) {
            if (event.key !== 'Escape') return;
            var near = document.activeElement && document.activeElement.closest ? document.activeElement.closest('.sl-dial.sl-open') : null;
            setDialsOff(null);
            if (near && near.querySelector('.sl-dial-toggle')) near.querySelector('.sl-dial-toggle').focus();
        });
        // An item a hover showed keeps its fan open while it holds the focus, and the fan folds once the focus has left it
        document.addEventListener('focusin', function (event) {
            var item = event.target && event.target.closest ? event.target.closest('.sl-dial-item') : null;
            var dial = item ? item.closest('.sl-dial') : null;
            if (!dial || dial.classList.contains('sl-open')) return;
            setDialsOff(dial);
            setDialState(dial, true);
        });
        document.addEventListener('focusout', function (event) {
            var dial = event.target && event.target.closest ? event.target.closest('.sl-dial.sl-open') : null;
            if (!dial || (event.relatedTarget && dial.contains(event.relatedTarget))) return;
            deleteDialPoint(dial);
        });
        document.addEventListener('scroll', function () {
            document.querySelectorAll('.sl-dial.sl-dial-point').forEach(deleteDialPoint);
        }, true);
        document.addEventListener('click', function (event) {
            var node = event.target;
            if (!node || !node.closest) return;
            var ask = node.closest('[data-sl-confirm]');
            // An htmx element asks through htmx:confirm below, where the answer can hold the request back; a dialog raised here would run beside a request already sent
            if (ask && isHtmxOwn(ask)) ask = null;
            if (ask) {
                var dlg = document.getElementById('sl-confirm');
                if (dlg) {
                    event.preventDefault();
                    dlg.querySelector('[data-sl-confirm-text]').textContent = ask.getAttribute('data-sl-confirm');
                    dlg.slask = ask;
                    dlg.slrun = null;
                    setWindowOpen(dlg);
                    return;
                }
                if (!window.confirm(ask.getAttribute('data-sl-confirm'))) {
                    event.preventDefault();
                    return;
                }
            }
            var toggle = node.closest('.sl-dial-toggle');
            var own = toggle ? toggle.parentNode : null;
            var open = !!own && own.classList.contains('sl-open') && !own.classList.contains('sl-dial-point');
            setDialsOff(own);
            if (!own) return;
            // A press on the toggle brings the fan back to the place it belongs to, and only a fan already standing there closes on it
            deleteDialPoint(own);
            setDialState(own, !open);
        });
    }

    window.TranslateLang = function (input, output, lang, info, key) {
        var source = document.querySelector('input.' + input);
        var txt = source ? source.value.trim() : '';
        if (!txt) {
            window.alert(info);
            return;
        }

        lang = lang.toLowerCase().trim();
        var parts = lang.split(/[-_|]/);
        if (parts.length !== 2) {
            window.alert('Wrong language format: ' + lang);
            return;
        }

        var from = parts[0].substring(0, 2);
        var to = parts[1].substring(0, 2);
        var url = 'https://api.mymemory.translated.net/get';
        var hasHTML = /<[^>]+>/.test(txt);

        if (!hasHTML) {
            getJsonData(url + '?q=' + encodeURIComponent(txt) + '&langpair=' + encodeURIComponent(from + '|' + to))
                .then(function (res) {
                    var translated = ((res && res.responseData && res.responseData.translatedText) || txt)
                        .replace(/&nbsp;/g, ' ')
                        .replace(/\s+/g, ' ')
                        .trim();
                    setInputValueByClass(output, translated);
                })
                .catch(function () {
                    window.alert('Translation request failed');
                });
            return;
        }

        var hold = document.createElement('template');
        hold.innerHTML = txt;
        var nodes = [];

        (function checkScan(node) {
            if (node.nodeType === 3 && node.nodeValue.trim() !== '') {
                nodes.push(node);
                return;
            }
            for (var i = 0; i < node.childNodes.length; i++) {
                checkScan(node.childNodes[i]);
            }
        })(hold.content);

        var index = 0;
        function setProcessNext() {
            if (index >= nodes.length) {
                setInputValueByClass(output, hold.innerHTML);
                return;
            }
            var original = nodes[index].nodeValue.trim();
            getJsonData(url + '?q=' + encodeURIComponent(original) + '&langpair=' + encodeURIComponent(from + '|' + to))
                .then(function (res) {
                    if (res && res.responseData && res.responseData.translatedText) {
                        nodes[index].nodeValue = res.responseData.translatedText;
                    }
                })
                .catch(function () {
                })
                .finally(function () {
                    index++;
                    window.setTimeout(setProcessNext, 100);
                });
        }

        setProcessNext();
    };

    var floatEdge = 12;
    var floatGap = 8;
    var floatStates = ['sl-float-left', 'sl-float-right', 'sl-float-up', 'sl-is-open'];

    // Drop only the state classes: data-sl-float-placed outlives them, so the panel keeps its measured spot and fades out in place with no jump back to the static one
    function clearFloatState(node) {
        for (var i = 0; i < floatStates.length; i++) node.classList.remove(floatStates[i]);
    }

    function getFloatPanel(node) {
        return node.querySelector(':scope > .sl-float-panel');
    }

    // Open a float: the script writes the two measured coordinates and the theme owns the rule that reads them; data-sl-float-up opens it upwards where it fits
    function placeFloat(node) {
        var panel = getFloatPanel(node);
        if (!panel) return;

        node.removeAttribute('data-sl-float-shut');
        if (node.slFloatClose) {
            window.clearTimeout(node.slFloatClose);
            node.slFloatClose = null;
        }
        clearFloatState(node);
        node.classList.add('sl-is-open');

        var placed = node.getAttribute('data-sl-float-placed') === '1';
        if (!placed) {
            panel.style.setProperty('--sl-d-float-left', '-9999px');
            panel.style.setProperty('--sl-d-float-top', '-9999px');
            node.setAttribute('data-sl-float-placed', '1');
        }

        window.requestAnimationFrame(function () {
            var rect = panel.getBoundingClientRect();
            var host = node.getBoundingClientRect();
            var width = rect.width;
            var height = rect.height;
            var anchor = node.closest('[data-sl-float-align]');
            var align = anchor ? anchor.getAttribute('data-sl-float-align') : '';
            var left = (align === 'end') ? host.right - width : host.left + (host.width / 2) - (width / 2);
            var top = host.bottom + floatGap;
            var center = host.left + (host.width / 2);
            var high = !!node.closest('[data-sl-float-up]');

            if (left + width > window.innerWidth - floatEdge) {
                left = Math.max(floatEdge, Math.min(host.right, window.innerWidth - floatEdge) - width);
                node.classList.add('sl-float-right');
            } else if (left < floatEdge) {
                left = floatEdge;
                node.classList.add('sl-float-left');
            }

            if ((high || top + height > window.innerHeight - floatEdge) && host.top > height + floatEdge) {
                top = host.top - height - floatGap;
                node.classList.add('sl-float-up');
            }

            var arrow = Math.max(14, Math.min(width - 14, center - left));
            panel.style.setProperty('--sl-d-float-left', left + 'px');
            panel.style.setProperty('--sl-d-float-top', top + 'px');
            panel.style.setProperty('--sl-d-arrow', arrow + 'px');
        });
    }

    function refitFloating() {
        var list = document.querySelectorAll('.sl-float.sl-is-open');
        for (var i = 0; i < list.length; i++) placeFloat(list[i]);
    }

    // Wire hover/focus float opening; closing waits for a grace delay and for focus to leave, so the cursor can cross the gap into the panel and typing inside never closes it
    function setFloating(node) {
        var root = node && node.nodeType ? node : document;
        var list = root.querySelectorAll ? root.querySelectorAll('.sl-float:not([data-sl-float-event="click"])') : [];
        for (var i = 0; i < list.length; i++) {
            (function (item) {
                if (item.getAttribute('data-sl-float-ready') === '1') return;
                item.setAttribute('data-sl-float-ready', '1');
                item.addEventListener('mouseenter', function () { placeFloat(item); });
                item.addEventListener('focusin', function () {
                    if (item.slFloatHush) {
                        item.slFloatHush = false;
                        return;
                    }
                    placeFloat(item);
                });
                item.addEventListener('mouseleave', function () {
                    if (item.contains(document.activeElement)) return;
                    if (item.slFloatClose) window.clearTimeout(item.slFloatClose);
                    item.slFloatClose = window.setTimeout(function () {
                        item.slFloatClose = null;
                        clearFloatState(item);
                    }, 300);
                });
                item.addEventListener('focusout', function (e) {
                    if (e.relatedTarget && item.contains(e.relatedTarget)) return;
                    clearFloatState(item);
                });
            }(list[i]));
        }
    }

    // Close hover-model floats on a tap or click outside: touch has no mouseleave, so this is the only reliable close path on coarse pointers
    function setFloatOutsideClose() {
        if (document.documentElement.getAttribute('data-sl-float-outside-ready') === '1') return;
        document.documentElement.setAttribute('data-sl-float-outside-ready', '1');
        document.addEventListener('pointerdown', function (e) {
            var list = document.querySelectorAll('.sl-float.sl-is-open:not([data-sl-float-event="click"])');
            for (var i = 0; i < list.length; i++) {
                if (!list[i].contains(e.target)) clearFloatState(list[i]);
            }
        });
        // WCAG 1.4.13: Escape shuts an open float even under the pointer or the focus, and a focus inside its panel goes back to its launcher
        document.addEventListener('keydown', function (e) {
            if (e.key !== 'Escape') return;
            var list = document.querySelectorAll('.sl-float.sl-is-open:not([data-sl-float-event="click"])');
            for (var i = 0; i < list.length; i++) {
                var item = list[i];
                var panel = getFloatPanel(item);
                var back = item.matches('[tabindex]') ? item : item.querySelector('button, [href], [tabindex]');
                e.preventDefault();
                if (panel && back && panel.contains(document.activeElement)) {
                    item.slFloatHush = true;
                    back.focus();
                }
                clearFloatState(item);
                item.setAttribute('data-sl-float-shut', '');
            }
        });
    }

    function initTabGroup(group) {
        var list = Array.prototype.slice.call(group.querySelectorAll('[data-sl-tab-link][data-sl-tab-target]'));
        if (!list.length) return;
        var saveGroup = group.getAttribute('data-sl-tabs-init') || group.id || 'tabs';
        var save = 'slaed-tabs:' + saveGroup;
        var syncSelector = group.getAttribute('data-sl-tabs-sync');

        function pick(link) {
            var rel = link.getAttribute('data-sl-tab-target');
            if (!rel) return;
            var index = list.indexOf(link);
            for (var i = 0; i < list.length; i++) {
                var item = list[i];
                var show = item === link;
                var target = item.getAttribute('data-sl-tab-target');
                var pane = group.querySelector('[data-sl-tab-panel="' + target + '"]') || document.getElementById(target);
                item.classList.toggle('sl-is-active', show);
                item.setAttribute('aria-selected', show ? 'true' : 'false');
                item.setAttribute('tabindex', show ? '0' : '-1');
                if (pane) {
                    pane.style.display = show ? 'block' : 'none';
                    pane.hidden = !show;
                    pane.setAttribute('aria-hidden', show ? 'false' : 'true');
                }
            }
            var shows = document.querySelectorAll('[data-sl-tab-show][data-sl-tab-group="' + saveGroup + '"]');
            for (var s = 0; s < shows.length; s++) {
                shows[s].hidden = shows[s].getAttribute('data-sl-tab-show') !== String(index);
            }
            try {
                window.sessionStorage.setItem(save, String(index));
            } catch (err) {
            }
            if (syncSelector) {
                var sync = document.querySelectorAll(syncSelector);
                for (var k = 0; k < sync.length; k++) {
                    if ('value' in sync[k]) sync[k].value = String(index);
                }
            }
            var infos = document.querySelectorAll('[data-sl-tab-info-link="' + saveGroup + '"]');
            for (var j = 0; j < infos.length; j++) {
                try {
                    var url = new URL(infos[j].href, window.location.href);
                    url.searchParams.set('tab', String(index));
                    infos[j].href = url.toString();
                } catch (err) {
                }
            }
        }

        for (var i = 0; i < list.length; i++) {
            list[i].setAttribute('role', 'tab');
            list[i].onclick = function () {
                pick(this);
                return false;
            };
            list[i].onkeydown = function (event) {
                var pos = list.indexOf(this);
                if (event.key === 'ArrowRight') {
                    event.preventDefault();
                    pick(list[(pos + 1) % list.length]);
                } else if (event.key === 'ArrowLeft') {
                    event.preventDefault();
                    pick(list[(pos + list.length - 1) % list.length]);
                }
            };
            var target = list[i].getAttribute('data-sl-tab-target');
            var pane = group.querySelector('[data-sl-tab-panel="' + target + '"]') || document.getElementById(target);
            if (pane) pane.setAttribute('role', 'tabpanel');
        }

        var idx = -1;
        var attr = parseInt(group.getAttribute('data-sl-tabs-index'), 10);
        if (!isNaN(attr) && list[attr]) idx = attr;
        if (idx < 0) {
            try {
                idx = parseInt(window.sessionStorage.getItem(save), 10);
            } catch (err) {
            }
        }
        if (isNaN(idx) || !list[idx]) {
            idx = list.findIndex(function (item) {
                return item.classList.contains('sl-is-active');
            });
        }
        if (idx < 0) idx = 0;
        pick(list[idx]);

        var nav = group.querySelector('.sl-tabs-nav');
        if (nav) {
            nav.setAttribute('role', 'tablist');
            nav.addEventListener('wheel', function (event) {
                if (this.scrollWidth <= this.clientWidth) return;
                if (Math.abs(event.deltaY) <= Math.abs(event.deltaX) && event.deltaX === 0) return;
                event.preventDefault();
                this.scrollLeft += event.deltaX || event.deltaY;
            }, { passive: false });
        }
    }

    // Show a row only for one value of another field: the tab hook keys off the tab index and cannot express this, so the value of the named control decides
    function applyFieldShow(field) {
        var form = field.form || document;
        var rows = form.querySelectorAll('[data-sl-show-when="' + field.name + '"]');
        for (var i = 0; i < rows.length; i++) {
            var want = (rows[i].getAttribute('data-sl-show-value') || '').split(' ');
            rows[i].style.display = (want.indexOf(field.value) < 0) ? 'none' : '';
        }
    }

    // Progressive by construction: a row is written visible and is only ever hidden here, so a page without this script offers every setting
    function setFieldShows(node) {
        var root = node && node.querySelectorAll ? node : document;
        var rows = root.querySelectorAll('[data-sl-show-when]');
        for (var i = 0; i < rows.length; i++) {
            var form = rows[i].closest('form') || document;
            var field = form.querySelector('[name="' + rows[i].getAttribute('data-sl-show-when') + '"]');
            if (!field) continue;
            if (field.getAttribute('data-sl-show-ready') !== '1') {
                field.setAttribute('data-sl-show-ready', '1');
                field.addEventListener('change', function () {
                    applyFieldShow(this);
                });
            }
            applyFieldShow(field);
        }
    }

    // Repeatable rows: the server writes every row and marks the empty ones, so a page without this script shows them all and the form works the same
    function setRepeatRows(node) {
        var root = node && node.querySelectorAll ? node : document;
        var groups = root.querySelectorAll('[data-sl-repeat]');
        for (var i = 0; i < groups.length; i++) {
            var box = groups[i];
            if (box.getAttribute('data-sl-repeat-ready') === '1') continue;
            box.setAttribute('data-sl-repeat-ready', '1');
            var add = box.querySelector(':scope > [data-sl-repeat-add]');
            var rows = box.querySelectorAll(':scope > [data-sl-repeat-row][data-sl-repeat-empty]');
            for (var j = 1; j < rows.length; j++) rows[j].hidden = true;
            if (!add || rows.length < 2) continue;
            add.hidden = false;
            add.addEventListener('click', function () {
                var group = this.closest('[data-sl-repeat]');
                var next = group ? group.querySelector(':scope > [data-sl-repeat-row][hidden]') : null;
                if (next) {
                    next.hidden = false;
                    var pick = next.querySelector('input:not([type="hidden"]), select, textarea');
                    if (pick) pick.focus();
                }
                if (!group || !group.querySelector(':scope > [data-sl-repeat-row][hidden]')) this.hidden = true;
            });
        }
    }

    function setTabs(node) {
        var root = node && node.querySelectorAll ? node : document;
        var groups = root.querySelectorAll('[data-sl-tabs-init]');
        for (var i = 0; i < groups.length; i++) {
            if (groups[i].getAttribute('data-sl-tabs-ready') === '1') continue;
            groups[i].setAttribute('data-sl-tabs-ready', '1');
            initTabGroup(groups[i]);
        }
    }

    // Profile activity feeds: clone entries once and auto-scroll the visible list; hidden tab panels are measured after their tab becomes active
    function initProfileScroll(view) {
        if (view.getAttribute('data-sl-profile-ready') === '1' || view.offsetParent === null) return;
        var feed = view.querySelector('.sl-profile-scroll-feed');
        if (!feed) return;
        view.setAttribute('data-sl-profile-ready', '1');
        var items = Array.prototype.slice.call(feed.children);
        if (items.length <= 4 || isMotionStill()) return;
        items.forEach(function (item) {
            var clone = item.cloneNode(true);
            clone.setAttribute('aria-hidden', 'true');
            feed.appendChild(clone);
        });
        window.requestAnimationFrame(function () {
            view.style.setProperty('--sl-d-duration', Math.max(15, items.length * 3) + 's');
            view.classList.add('sl-is-scrolling');
        });
    }

    function setProfileScrolls(root) {
        var scope = root && root.querySelectorAll ? root : document;
        var views = scope.querySelectorAll('[data-sl-profile-scroll]');
        for (var i = 0; i < views.length; i++) initProfileScroll(views[i]);
    }

    // A section counts as read when its top has passed the fold but its body still fills the screen, the band the eye is actually in
    function setSectionSpy(node) {
        var root = node && node.querySelectorAll ? node : document;
        var list = root.querySelectorAll('[data-sl-spy]');
        for (var i = 0; i < list.length; i++) setSpyRail(list[i]);
    }

    // The band cannot reach a short last section, so the bottom of the document reads as the last mark; a px rail publishes the height of its dock
    function setSpyRail(rail) {
        if (rail.getAttribute('data-sl-spy-ready') === '1' || !window.IntersectionObserver) return;
        var marks = rail.querySelectorAll('[data-sl-spy-mark]');
        var secs = [];
        for (var i = 0; i < marks.length; i++) {
            var sect = document.getElementById(marks[i].getAttribute('data-sl-spy-mark'));
            if (!sect) return;
            secs.push(sect);
        }
        if (!secs.length) return;
        rail.setAttribute('data-sl-spy-ready', '1');
        var at = 0;
        var px = rail.getAttribute('data-sl-spy') === 'px';
        var dock = px && rail.parentElement ? rail.parentElement : rail;
        // The road is measured to the icon of the current mark, not derived: the marks land where their fractions put them
        var draw = function () {
            for (var j = 0; j < marks.length; j++) {
                marks[j].setAttribute('aria-current', j === at ? 'true' : 'false');
                marks[j].setAttribute('data-sl-spy-done', j < at ? '1' : '0');
            }
            var mark = marks[at];
            if (!mark) return;
            if (px) {
                rail.style.setProperty('--sl-d-spy', (mark.offsetLeft + mark.offsetWidth / 2) + 'px');
                document.documentElement.style.setProperty('--sl-d-rail', dock.getBoundingClientRect().height + 'px');
                if (mark.offsetLeft < rail.scrollLeft || mark.offsetLeft + mark.offsetWidth > rail.scrollLeft + rail.clientWidth) {
                    var still = isMotionStill();
                    rail.scrollTo({ left: mark.offsetLeft - rail.clientWidth / 2 + mark.offsetWidth / 2, behavior: still ? 'instant' : 'smooth' });
                }
                return;
            }
            var icon = mark.querySelector('.bi');
            if (!icon) return;
            var box = rail.getBoundingClientRect();
            var dot = icon.getBoundingClientRect();
            if (box.width) rail.style.setProperty('--sl-d-spy', ((dot.left + dot.width / 2 - box.left) / box.width * 100) + '%');
        };
        window.addEventListener('resize', draw, { passive: true });
        var watch = new IntersectionObserver(function (rows) {
            for (var k = 0; k < rows.length; k++) {
                if (!rows[k].isIntersecting) continue;
                at = secs.indexOf(rows[k].target);
                draw();
            }
        }, { rootMargin: '-20% 0px -70% 0px' });
        for (var n = 0; n < secs.length; n++) watch.observe(secs[n]);
        window.addEventListener('scroll', function () {
            if (at === marks.length - 1 || window.innerHeight + window.scrollY < document.documentElement.scrollHeight - 2) return;
            at = marks.length - 1;
            draw();
        }, { passive: true });
        rail.addEventListener('click', function (event) {
            var mark = event.target && event.target.closest ? event.target.closest('[data-sl-spy-mark]') : null;
            if (!mark) return;
            event.preventDefault();
            var sect = document.getElementById(mark.getAttribute('data-sl-spy-mark'));
            if (sect) sect.scrollIntoView({ block: 'start', behavior: isMotionStill() ? 'auto' : 'smooth' });
        });
        draw();
    }

    // The place a page or a swap hands to the next one for the plate of each segmented switch, by the name of its rail
    var knobkey = 'slaed-knob:place';

    // The plate of a rail (data-sl-knob) goes where its chosen cell stands: the script measures, the sheet moves it
    function setKnobPlace(rail) {
        var cell = null;
        Array.prototype.some.call(rail.querySelectorAll('.sl-is-active'), function (one) {
            if (one.closest('[data-sl-knob-mark]') || !one.offsetParent) return false;
            cell = one;
            return true;
        });
        if (!cell) return;
        var box = rail.getBoundingClientRect();
        var rect = cell.getBoundingClientRect();
        rail.style.setProperty('--sl-d-knob-x', (rect.left - box.left - rail.clientLeft) + 'px');
        rail.style.setProperty('--sl-d-knob-w', rect.width + 'px');
    }

    // The rails under a node, the node itself included, since an outer swap hands over the rail it replaces
    function getKnobRails(node) {
        var root = node && node.querySelectorAll ? node : document;
        var list = Array.prototype.slice.call(root.querySelectorAll('[data-sl-knob]'));
        if (root.matches && root.matches('[data-sl-knob]')) list.unshift(root);
        return list;
    }

    // The places of the rails under a node, kept for the page or the swap that comes next; the first rail of a name speaks for it
    function setKnobKeep(node) {
        var keep = {};
        var some = false;
        getKnobRails(node).forEach(function (rail) {
            var name = rail.getAttribute('data-sl-knob');
            var wide = rail.style.getPropertyValue('--sl-d-knob-w');
            if (!name || keep[name] || !wide) return;
            keep[name] = { x: rail.style.getPropertyValue('--sl-d-knob-x'), w: wide };
            some = true;
        });
        if (!some) return;
        try {
            window.sessionStorage.setItem(knobkey, JSON.stringify({ path: window.location.pathname, time: Date.now(), rails: keep }));
        } catch (err) {
        }
    }

    // The places handed over, read once and only on the same path within five seconds, so none wanders into another page
    function getKnobFrom() {
        var from = {};
        try {
            from = JSON.parse(window.sessionStorage.getItem(knobkey) || '{}') || {};
            window.sessionStorage.removeItem(knobkey);
        } catch (err) {
            return {};
        }
        if (from.path !== window.location.pathname || Date.now() - (from.time || 0) > 5000 || !from.rails) return {};
        return from.rails;
    }

    // A new rail stands still once on the handed place, then travels; observers that answer at once leave a still plate alone
    function setKnobs(node) {
        var rails = getKnobRails(node).filter(function (rail) { return !rail.hasAttribute('data-sl-knob-ready'); });
        if (!rails.length) return;
        var from = getKnobFrom();
        rails.forEach(function (rail) {
            var was = from[rail.getAttribute('data-sl-knob')];
            rail.setAttribute('data-sl-knob-still', '');
            if (was) {
                rail.style.setProperty('--sl-d-knob-x', was.x);
                rail.style.setProperty('--sl-d-knob-w', was.w);
            } else {
                setKnobPlace(rail);
            }
            rail.setAttribute('data-sl-knob-ready', '');
            window.requestAnimationFrame(function () {
                window.requestAnimationFrame(function () {
                    rail.removeAttribute('data-sl-knob-still');
                    setKnobPlace(rail);
                });
            });
            var follow = function () {
                if (!rail.hasAttribute('data-sl-knob-still')) setKnobPlace(rail);
            };
            if (window.ResizeObserver) new ResizeObserver(follow).observe(rail);
            new MutationObserver(follow).observe(rail, { subtree: true, childList: true, attributes: true, attributeFilter: ['class'] });
        });
    }

    window.addEventListener('pagehide', function () {
        setKnobKeep(document);
    });

    document.addEventListener('htmx:beforeSwap', function (event) {
        if (event.detail && event.detail.target) setKnobKeep(event.detail.target);
    });

    // A rail an answer swaps out of band, as the filter of the watch window, hands its place over and travels like any other
    document.addEventListener('htmx:oobBeforeSwap', function (event) {
        if (event.detail && event.detail.target) setKnobKeep(event.detail.target);
    });

    document.addEventListener('htmx:oobAfterSwap', function () {
        setKnobs(document);
    });

    // A data-sl-sticky block wears data-sl-stuck once its mark passes its top line, the band reaching down so no jump skips it; unsticking closes the lens
    function setStickyBars(node) {
        var root = node && node.querySelectorAll ? node : document;
        var list = Array.prototype.slice.call(root.querySelectorAll('[data-sl-sticky]'));
        if (root.matches && root.matches('[data-sl-sticky]')) list.unshift(root);
        if (!window.IntersectionObserver) return;
        list.forEach(function (bar) {
            if (bar.hasAttribute('data-sl-sticky-ready')) return;
            bar.setAttribute('data-sl-sticky-ready', '');
            var mark = bar.querySelector('[data-sl-sticky-mark]');
            if (!mark) {
                mark = document.createElement('div');
                mark.setAttribute('data-sl-sticky-mark', '');
                mark.setAttribute('aria-hidden', 'true');
                bar.before(mark);
            }
            var top = parseFloat(window.getComputedStyle(bar).top) || 0;
            var watch = new IntersectionObserver(function (rows) {
                var row = rows[rows.length - 1];
                if (!bar.isConnected) {
                    watch.disconnect();
                    return;
                }
                var stuck = !row.isIntersecting && row.boundingClientRect.top < top;
                bar.toggleAttribute('data-sl-stuck', stuck);
                if (!stuck && bar.hasAttribute('data-sl-lens-open')) setLensState(bar, false);
            }, { rootMargin: (-top) + 'px 0px 1000000px 0px' });
            watch.observe(mark);
        });
    }

    // A swapped part marked data-sl-swap-reveal whose head went above the screen jumps back to it, past the smooth scrolling of the sheet; a part in sight stays
    function setSwapReveal(node) {
        if (!node || !node.isConnected || !node.matches || !node.matches('[data-sl-swap-reveal]')) return;
        if (node.getBoundingClientRect().top < 0) node.scrollIntoView({ block: 'start', behavior: 'instant' });
    }

    // The bar carries the state of its lens and every lens button in it tells it
    function setLensState(bar, open) {
        bar.toggleAttribute('data-sl-lens-open', open);
        var list = bar.querySelectorAll('[data-sl-lens]');
        for (var i = 0; i < list.length; i++) list[i].setAttribute('aria-expanded', open ? 'true' : 'false');
    }

    // Closing a lens by its button or by Esc drops what was typed, told to the field as input so whatever reads it follows
    function setLensDrop(bar, field) {
        setLensState(bar, false);
        if (field.value === '') return;
        field.value = '';
        field.dispatchEvent(new Event('input', { bubbles: true }));
    }

    // A button with data-sl-lens="<id>" inside a sticky bar opens the field of that id over the bar and focuses it; Esc in the field closes it
    function setLensKeys() {
        document.addEventListener('click', function (event) {
            var lens = event.target && event.target.closest ? event.target.closest('[data-sl-lens]') : null;
            var bar = lens ? lens.closest('[data-sl-sticky]') : null;
            var field = lens ? document.getElementById(lens.getAttribute('data-sl-lens') || '') : null;
            if (!bar || !field) return;
            event.preventDefault();
            if (bar.hasAttribute('data-sl-lens-open')) {
                setLensDrop(bar, field);
                return;
            }
            setLensState(bar, true);
            field.focus();
        });
        document.addEventListener('keydown', function (event) {
            if (event.key !== 'Escape' || !event.target || !event.target.id || !event.target.closest) return;
            var bar = event.target.closest('[data-sl-lens-open]');
            var lens = bar ? bar.querySelector('[data-sl-lens="' + CSS.escape(event.target.id) + '"]') : null;
            if (!lens) return;
            event.preventDefault();
            setLensDrop(bar, event.target);
            lens.focus();
        });
    }

    // The chosen hint is told to the field by aria-activedescendant while the focus stays in the field, so typing goes on
    function setHintActive(field, list, item) {
        var opts = list.querySelectorAll('[role="option"]');
        for (var i = 0; i < opts.length; i++) opts[i].setAttribute('aria-selected', opts[i] === item ? 'true' : 'false');
        if (!item) {
            field.removeAttribute('aria-activedescendant');
            return;
        }
        field.setAttribute('aria-activedescendant', item.id);
    }

    // A hint list is shown only with options in it, and the field says whether it stands open
    function setHintOpen(field, list, open) {
        var isOpen = open && !!list.querySelector('[role="option"]');
        list.hidden = !isOpen;
        field.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
        setHintActive(field, list, null);
    }

    // A swap into the list a field names by data-sl-hints opens it while the field still has the focus, every option with an id of its own
    function setHintList(list) {
        if (!list || !list.id || !list.isConnected) return;
        var field = document.querySelector('[data-sl-hints="' + CSS.escape(list.id) + '"]');
        if (!field) return;
        var opts = list.querySelectorAll('[role="option"]');
        for (var i = 0; i < opts.length; i++) if (!opts[i].id) opts[i].id = list.id + '-' + i;
        setHintOpen(field, list, document.activeElement === field);
    }

    // Arrows walk the hints round, Enter opens the chosen one, Esc closes it before the lens hears it; a press on it keeps the focus, so a browser not focusing links clicks
    function setHintKeys() {
        var getList = function (node) {
            var field = node && node.closest ? node.closest('[data-sl-hints]') : null;
            var box = !field && node && node.closest ? node.closest('[role="listbox"][id]') : null;
            if (box) field = document.querySelector('[data-sl-hints="' + CSS.escape(box.id) + '"]');
            var list = field ? document.getElementById(field.getAttribute('data-sl-hints') || '') : null;
            return list ? { field: field, list: list } : null;
        };
        document.addEventListener('keydown', function (event) {
            var pair = getList(event.target);
            if (!pair || pair.list.hidden) return;
            var opts = Array.prototype.slice.call(pair.list.querySelectorAll('[role="option"]'));
            var now = opts.indexOf(pair.list.querySelector('[aria-selected="true"]'));
            if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
                event.preventDefault();
                var step = event.key === 'ArrowDown' ? 1 : -1;
                setHintActive(pair.field, pair.list, opts[now < 0 ? (step > 0 ? 0 : opts.length - 1) : (now + step + opts.length) % opts.length]);
            } else if (event.key === 'Enter' && now >= 0) {
                event.preventDefault();
                opts[now].click();
            } else if (event.key === 'Escape') {
                event.preventDefault();
                event.stopPropagation();
                setHintOpen(pair.field, pair.list, false);
            }
        }, true);
        document.addEventListener('mousedown', function (event) {
            var pair = getList(event.target);
            if (pair && pair.list.contains(event.target)) event.preventDefault();
        });
        document.addEventListener('focusout', function (event) {
            var pair = getList(event.target);
            if (pair && event.relatedTarget !== pair.field && !pair.list.contains(event.relatedTarget)) setHintOpen(pair.field, pair.list, false);
        });
        document.addEventListener('focusin', function (event) {
            var pair = getList(event.target);
            if (pair && pair.field === event.target && pair.field.value.trim() !== '') setHintOpen(pair.field, pair.list, true);
        });
    }

    // The arrows click the back or forward link of the first pager on show outside a window; fields, tabs, windows and the file desk keep theirs
    function setPagerKeys() {
        document.addEventListener('keydown', function (event) {
            var rel = event.key === 'ArrowLeft' ? 'prev' : (event.key === 'ArrowRight' ? 'next' : '');
            var node = event.target;
            if (!rel || event.defaultPrevented || event.altKey || event.ctrlKey || event.metaKey || event.shiftKey) return;
            if (node && node.isContentEditable) return;
            if (node && node.closest && node.closest('input, textarea, select, audio, video, [role="tab"], [role="tablist"], dialog, .sl-fm-split')) return;
            var list = document.querySelectorAll('.sl-pager');
            for (var i = 0; i < list.length; i++) {
                if (list[i].closest('dialog') || !list[i].getClientRects().length) continue;
                var link = list[i].querySelector('.sl-pager-nav > a[rel="' + rel + '"]');
                if (!link) return;
                event.preventDefault();
                link.click();
                return;
            }
        });
    }

    // The fill meter draws the rule the server drew, so the figure the page arrives with and the one after the first keystroke cannot drift apart
    function setFillMeters() {
        var meters = document.querySelectorAll('[data-sl-meter]');
        var fields = document.querySelectorAll('[data-sl-meter-fill]');
        if (!meters.length || !fields.length) return;
        var draw = function () {
            var full = 0;
            for (var i = 0; i < fields.length; i++) {
                var valu = (fields[i].value || '').trim();
                if (valu !== '' && valu !== fields[i].getAttribute('data-sl-meter-fill')) full++;
            }
            var rate = Math.round(full / fields.length * 100);
            for (var j = 0; j < meters.length; j++) {
                var item = meters[j];
                item.style.setProperty('--sl-d-meter', String(rate));
                if (item.classList.contains('sl-knob')) item.classList.toggle('sl-knob-full', rate >= 100);
                var num = item.querySelector('[data-sl-meter-num]');
                if (num) num.textContent = rate + '%';
                var rest = item.querySelector('[data-sl-meter-left]');
                if (rest) rest.textContent = String(fields.length - full);
            }
        };
        document.addEventListener('input', draw);
        document.addEventListener('change', draw);
        draw();
    }

    // Arm every form that tracks its changes once, here and never in the markup, so a page without this script keeps its save bar shown
    function setDirtyForms(node) {
        var root = node && node.querySelectorAll ? node : document;
        var list = root.querySelectorAll('[data-sl-dirty]');
        for (var i = 0; i < list.length; i++) {
            if (!list[i].slArmed) setDirtyScope(list[i]);
        }
    }

    // A control that saves itself the moment it changes - the gallery pick posting over htmx - is not an unsaved change
    function setDirtyScope(scope) {
        var wake = function (event) {
            var node = event.target;
            if (!node || !node.closest || isDirtyFree(node)) return;
            if (node === scope || node.isContentEditable) {
                scope.slTouch = true;
                if (node.closest('[data-sl-tab-panel]')) node.closest('[data-sl-tab-panel]').slTouch = true;
            } else if (!node.name) {
                return;
            }
            setDirtyState(scope);
        };
        scope.slArmed = true;
        scope.slTouch = scope.getAttribute('data-sl-dirty') === '1';
        getDirtyFields(scope).forEach(function (field) { field.slSaved = getDirtyValue(field); });
        scope.setAttribute('data-sl-dirty', scope.slTouch ? '1' : '0');
        scope.addEventListener('input', wake);
        scope.addEventListener('change', wake);
        scope.addEventListener('click', function (event) {
            var back = event.target && event.target.closest ? event.target.closest('[data-sl-clean]') : null;
            if (!back) return;
            event.preventDefault();
            if (typeof scope.reset === 'function') scope.reset();
            setEditorsBack(scope);
            scope.slTouch = false;
            scope.querySelectorAll('[data-sl-tab-panel]').forEach(function (pane) { pane.slTouch = false; });
            setDirtyState(scope);
        });
    }

    // A pick of rows, the choice of what to do with them and a control saving itself over htmx change nothing the form would lose
    function isDirtyFree(node) {
        return !!node.closest('[hx-post], [data-sl-check-item], [data-sl-check-all], .sl-div-actions');
    }

    // The named controls of a form that carry its data; a hidden field is written by scripts and a tab index, never by a hand
    function getDirtyFields(scope) {
        return Array.prototype.filter.call(scope.querySelectorAll('input[name], select[name], textarea[name]'), function (field) {
            return field.type !== 'hidden' && field.type !== 'submit' && field.type !== 'button' && !isDirtyFree(field);
        });
    }

    // What a control holds now, in one string a later state can be told from
    function getDirtyValue(field) {
        if (field.type === 'checkbox' || field.type === 'radio') return String(field.checked);
        if (!field.multiple) return field.value;
        return Array.prototype.filter.call(field.options, function (one) { return one.selected; }).map(function (one) { return one.value; }).join(',');
    }

    // Changed is measured against the values the page brought, so a change put back leaves the form and its tab clean again
    function setDirtyState(scope) {
        var held = getDirtyFields(scope).filter(function (field) { return getDirtyValue(field) !== field.slSaved; });
        scope.setAttribute('data-sl-dirty', held.length || scope.slTouch ? '1' : '0');
        document.querySelectorAll('[data-sl-dirty-dot]').forEach(function (dot) {
            var link = dot.closest('[data-sl-tab-target]');
            var pane = link ? document.getElementById(link.getAttribute('data-sl-tab-target')) : null;
            if (!pane || !scope.contains(pane)) return;
            dot.hidden = !pane.slTouch && !held.some(function (field) { return pane.contains(field); });
        });
    }

    // The form whose changes a step away would lose, unless it is already on its way to the server or was let go
    function getDirtyHeld() {
        var list = document.querySelectorAll('[data-sl-leave][data-sl-dirty="1"]');
        for (var i = 0; i < list.length; i++) {
            if (!list[i].slFree) return list[i];
        }
        return null;
    }

    // Leaving a changed form asks first: a link and another form through the system dialog, a reload or a closed tab through the browser
    function setDirtyLeave() {
        document.addEventListener('click', function (event) {
            var link = event.target && event.target.closest ? event.target.closest('a[href]') : null;
            var held = link ? getDirtyHeld() : null;
            var href = link ? link.getAttribute('href') : '';
            if (!held || event.defaultPrevented || event.button !== 0 || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) return;
            if (href.charAt(0) === '#' || /^javascript:/i.test(href) || link.target === '_blank' || link.hasAttribute('download')) return;
            event.preventDefault();
            event.stopImmediatePropagation();
            window.setConfirmTask(held.getAttribute('data-sl-leave'), function () {
                held.slFree = true;
                link.click();
            });
        }, true);
        document.addEventListener('submit', function (event) {
            var form = event.target;
            var held = getDirtyHeld();
            var send = event.submitter || null;
            if (!held || form === held || form.closest('dialog') || form.matches('[hx-post], [hx-get], [hx-boost]')) return;
            event.preventDefault();
            event.stopImmediatePropagation();
            window.setConfirmTask(held.getAttribute('data-sl-leave'), function () {
                held.slFree = true;
                if (form.requestSubmit) form.requestSubmit(send);
                else form.submit();
            });
        }, true);
        document.addEventListener('submit', function (event) {
            if (!event.defaultPrevented && event.target.hasAttribute('data-sl-leave')) event.target.slFree = true;
        });
        window.addEventListener('beforeunload', function (event) {
            if (!getDirtyHeld()) return;
            event.preventDefault();
            event.returnValue = '';
        });
    }

    // A form reset puts the default back into the textarea, but an editor draws from its own instance and would write the typed text back over it
    function setEditorsBack(scope) {
        var api = window.SlaedToastUi;
        if (!api || typeof api.getEditor !== 'function') return;
        var list = scope.querySelectorAll('textarea[id]');
        for (var i = 0; i < list.length; i++) {
            var edit = api.getEditor(list[i].id);
            if (edit && typeof edit.setMarkdown === 'function') edit.setMarkdown(list[i].value);
        }
    }

    function setAlerts(node) {
        var root = node && node.querySelectorAll ? node : document;
        var list = root.querySelectorAll('[data-sl-autohide]');
        for (var i = 0; i < list.length; i++) {
            var item = list[i];
            if (item.getAttribute('data-sl-alert-ready') === '1') continue;
            item.setAttribute('data-sl-alert-ready', '1');
            var time = parseInt(item.getAttribute('data-sl-autohide'), 10);
            if (isNaN(time) || time < 1) time = 5000;
            window.setTimeout((function (el) {
                return function () {
                    if (!el || !el.parentNode) return;
                    el.classList.add('sl-is-hiding');
                    window.setTimeout(function () {
                        if (el && el.parentNode) el.parentNode.removeChild(el);
                    }, 400);
                };
            })(item), time);
        }
    }

    // Voting widgets: ease one numeric label from a start value to a target over 800ms
    function setVoteNumber(el, from, to, unit) {
        var start = performance.now();
        function tick(now) {
            var p = Math.min((now - start) / 800, 1);
            var val = from + (to - from) * (1 - Math.pow(1 - p, 3));
            el.textContent = unit ? val.toFixed(2) + unit : String(Math.round(val));
            if (p < 1) requestAnimationFrame(tick);
        }
        requestAnimationFrame(tick);
    }

    // Voting widgets: apply data-sl-percent/votes targets to bars and counters; first run counts up from zero
    function setVoteValues(vote, first) {
        var rows = vote.querySelectorAll('li[data-sl-percent]');
        var reduce = isMotionStill();
        for (var i = 0; i < rows.length; i++) {
            (function (row) {
                var pct = parseFloat(row.getAttribute('data-sl-percent')) || 0;
                var num = parseInt(row.getAttribute('data-sl-votes'), 10) || 0;
                var pel = row.querySelector('.sl-vote-pct');
                var vel = row.querySelector('.sl-vote-num');
                var bar = row.querySelector('.sl-progress-line div');
                if (bar) bar.style.width = pct.toFixed(2) + '%';
                if (reduce) {
                    if (pel) pel.textContent = pct.toFixed(2) + '%';
                    if (vel) vel.textContent = String(num);
                    return;
                }
                if (pel) setVoteNumber(pel, first ? 0 : parseFloat(pel.textContent) || 0, pct, '%');
                if (vel) setVoteNumber(vel, first ? 0 : parseInt(vel.textContent, 10) || 0, num, '');
            })(rows[i]);
        }
    }

    // Voting widgets: cold-start intro — bars and counters rise from zero after the first painted frame
    function setVoteIntro(vote) {
        if (isMotionStill()) return;
        var rows = vote.querySelectorAll('li[data-sl-percent]');
        if (!rows.length) return;
        for (var i = 0; i < rows.length; i++) {
            var bar = rows[i].querySelector('.sl-progress-line div');
            if (bar) bar.style.width = '0%';
        }
        requestAnimationFrame(function () {
            requestAnimationFrame(function () { setVoteValues(vote, true); });
        });
    }

    // Voting widgets: poll the widget endpoint every data-sl-vote-live seconds and tween rows to fresh values
    function setVoteLive(vote) {
        var wait = parseInt(vote.getAttribute('data-sl-vote-live'), 10) || 0;
        var url = vote.getAttribute('data-sl-vote-url') || '';
        if (wait < 1 || url === '' || vote.getAttribute('data-sl-vote-ready') === '1') return;
        vote.setAttribute('data-sl-vote-ready', '1');
        if (!vote.hasAttribute('data-sl-live-stamp')) vote.setAttribute('data-sl-live-stamp', Date.now());
        var timer = window.setInterval(function () {
            if (!document.body.contains(vote)) { window.clearInterval(timer); return; }
            if (vote.hasAttribute('data-sl-live-paused')) return;
            window.fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } }).then(function (response) {
                if (!response.ok) throw new Error('Request failed');
                return response.text();
            }).then(function (html) {
                var hold = document.createElement('template');
                hold.innerHTML = html;
                var fresh = hold.content.querySelector('.sl-vote');
                if (!fresh) return;
                var frows = fresh.querySelectorAll('li[data-sl-percent]');
                var rows = vote.querySelectorAll('li[data-sl-percent]');
                if (!frows.length || frows.length !== rows.length) {
                    window.clearInterval(timer);
                    vote.replaceWith(fresh);
                    setVoteBlocks(document);
                    return;
                }
                for (var i = 0; i < rows.length; i++) {
                    rows[i].setAttribute('data-sl-percent', frows[i].getAttribute('data-sl-percent'));
                    rows[i].setAttribute('data-sl-votes', frows[i].getAttribute('data-sl-votes'));
                    rows[i].classList.toggle('sl-lead', frows[i].classList.contains('sl-lead'));
                }
                setVoteValues(vote, false);
                vote.setAttribute('data-sl-live-stamp', Date.now());
                var votes = vote.querySelector('.sl-votes');
                var fvotes = fresh.querySelector('.sl-votes');
                if (votes && fvotes) votes.innerHTML = fvotes.innerHTML;
                var coms = vote.querySelector('.sl-coms');
                var fcoms = fresh.querySelector('.sl-coms');
                if (coms && fcoms) coms.innerHTML = fcoms.innerHTML;
            }).catch(function () {});
        }, wait * 1000);
    }

    // Voting widgets: entry point for initial load and htmx swaps
    function setVoteBlocks(root) {
        var votes = (root || document).querySelectorAll('.sl-vote');
        for (var i = 0; i < votes.length; i++) {
            if (votes[i].getAttribute('data-sl-vote-intro') !== '1') {
                votes[i].setAttribute('data-sl-vote-intro', '1');
                setVoteIntro(votes[i]);
            }
            setVoteLive(votes[i]);
        }
    }

    // QR generator: byte mode, ECC level L, penalty-based mask choice - compact port of Nayuki's qrcodegen (public domain)
    function getQrSvg(text) {
        var ECC = [-1, 7, 10, 15, 20, 26, 18, 20, 24, 30, 18, 20, 24, 26, 30, 22, 24, 28, 30, 28, 28, 28, 28, 30, 30, 26, 28, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30];
        var BLK = [-1, 1, 1, 1, 1, 1, 2, 2, 2, 2, 4, 4, 4, 4, 4, 6, 6, 6, 6, 7, 8, 8, 9, 9, 10, 12, 12, 12, 13, 14, 15, 16, 17, 18, 19, 19, 20, 21, 22, 24, 25];
        function raw(v) { var r = (16 * v + 128) * v + 64; if (v >= 2) { var a = Math.floor(v / 7) + 2; r -= (25 * a - 10) * a - 55; if (v >= 7) r -= 36; } return r; }
        function cap(v) { return Math.floor(raw(v) / 8) - ECC[v] * BLK[v]; }
        function gmul(x, y) { var z = 0; for (var i = 7; i >= 0; i--) { z = (z << 1) ^ ((z >>> 7) * 0x11D); z ^= ((y >>> i) & 1) * x; } return z & 255; }
        var bytes = Array.prototype.slice.call(new TextEncoder().encode(text));
        var ver;
        for (ver = 1; ver <= 40; ver++) if (4 + (ver < 10 ? 8 : 16) + bytes.length * 8 <= cap(ver) * 8) break;
        if (ver > 40) return '';
        var bits = [];
        function put(val, n) { for (var i = n - 1; i >= 0; i--) bits.push((val >>> i) & 1); }
        put(4, 4);
        put(bytes.length, ver < 10 ? 8 : 16);
        bytes.forEach(function (b) { put(b, 8); });
        var capbits = cap(ver) * 8;
        put(0, Math.min(4, capbits - bits.length));
        put(0, (8 - bits.length % 8) % 8);
        for (var pad = 0xEC; bits.length < capbits; pad ^= 0xEC ^ 0x11) put(pad, 8);
        var data = [];
        for (var i = 0; i < bits.length; i += 8) { var b = 0; for (var j = 0; j < 8; j++) b = (b << 1) | bits[i + j]; data.push(b); }
        var nb = BLK[ver], ecl = ECC[ver], rawcw = Math.floor(raw(ver) / 8);
        var nshort = nb - rawcw % nb, blocklen = Math.floor(rawcw / nb);
        var div = [];
        for (i = 0; i < ecl - 1; i++) div.push(0);
        div.push(1);
        var root = 1;
        for (i = 0; i < ecl; i++) {
            for (j = 0; j < div.length; j++) {
                div[j] = gmul(div[j], root);
                if (j + 1 < div.length) div[j] ^= div[j + 1];
            }
            root = gmul(root, 2);
        }
        var blocks = [], k = 0;
        for (i = 0; i < nb; i++) {
            var dat = data.slice(k, k + blocklen - ecl + (i < nshort ? 0 : 1));
            k += dat.length;
            var rem = div.map(function () { return 0; });
            dat.forEach(function (bb) {
                var factor = bb ^ rem.shift();
                rem.push(0);
                div.forEach(function (c, ci) { rem[ci] ^= gmul(c, factor); });
            });
            if (i < nshort) dat.push(0);
            blocks.push(dat.concat(rem));
        }
        var cw = [];
        for (i = 0; i < blocks[0].length; i++) for (j = 0; j < blocks.length; j++) if (i != blocklen - ecl || j >= nshort) cw.push(blocks[j][i]);
        var size = ver * 4 + 17, mods = [], fun = [];
        for (i = 0; i < size; i++) { mods.push(new Array(size).fill(false)); fun.push(new Array(size).fill(false)); }
        function setf(x, y, d) { mods[y][x] = d; fun[y][x] = true; }
        function bit(x, i) { return ((x >>> i) & 1) != 0; }
        for (i = 0; i < size; i++) { setf(6, i, i % 2 == 0); setf(i, 6, i % 2 == 0); }
        function finder(cx, cy) {
            for (var dy = -4; dy <= 4; dy++) for (var dx = -4; dx <= 4; dx++) {
                var xx = cx + dx, yy = cy + dy, dd = Math.max(Math.abs(dx), Math.abs(dy));
                if (xx >= 0 && xx < size && yy >= 0 && yy < size) setf(xx, yy, dd != 2 && dd != 4);
            }
        }
        finder(3, 3); finder(size - 4, 3); finder(3, size - 4);
        var ap = [];
        if (ver > 1) {
            var na = Math.floor(ver / 7) + 2;
            var step = (ver == 32) ? 26 : Math.ceil((ver * 4 + 4) / (na * 2 - 2)) * 2;
            ap = [6];
            for (var pos = size - 7; ap.length < na; pos -= step) ap.splice(1, 0, pos);
        }
        for (i = 0; i < ap.length; i++) for (j = 0; j < ap.length; j++) {
            if ((i == 0 && j == 0) || (i == 0 && j == ap.length - 1) || (i == ap.length - 1 && j == 0)) continue;
            for (var dy = -2; dy <= 2; dy++) for (var dx = -2; dx <= 2; dx++) setf(ap[i] + dx, ap[j] + dy, Math.max(Math.abs(dx), Math.abs(dy)) != 1);
        }
        function fmt(mask) {
            var d = (1 << 3) | mask, r = d;
            for (var i = 0; i < 10; i++) r = (r << 1) ^ ((r >>> 9) * 0x537);
            var f = ((d << 10) | r) ^ 0x5412;
            for (i = 0; i <= 5; i++) setf(8, i, bit(f, i));
            setf(8, 7, bit(f, 6)); setf(8, 8, bit(f, 7)); setf(7, 8, bit(f, 8));
            for (i = 9; i < 15; i++) setf(14 - i, 8, bit(f, i));
            for (i = 0; i < 8; i++) setf(size - 1 - i, 8, bit(f, i));
            for (i = 8; i < 15; i++) setf(8, size - 15 + i, bit(f, i));
            setf(8, size - 8, true);
        }
        fmt(0);
        if (ver >= 7) {
            var vr = ver;
            for (i = 0; i < 12; i++) vr = (vr << 1) ^ ((vr >>> 11) * 0x1F25);
            var vb = (ver << 12) | vr;
            for (i = 0; i < 18; i++) {
                var c = bit(vb, i), a = size - 11 + i % 3, d2 = Math.floor(i / 3);
                setf(a, d2, c); setf(d2, a, c);
            }
        }
        var idx = 0;
        for (var right = size - 1; right >= 1; right -= 2) {
            if (right == 6) right = 5;
            for (var vert = 0; vert < size; vert++) {
                for (j = 0; j < 2; j++) {
                    var x = right - j, up = ((right + 1) & 2) == 0;
                    var y = up ? size - 1 - vert : vert;
                    if (!fun[y][x] && idx < cw.length * 8) {
                        mods[y][x] = ((cw[idx >>> 3] >>> (7 - (idx & 7))) & 1) != 0;
                        idx++;
                    }
                }
            }
        }
        function maskbit(m, x, y) {
            switch (m) {
                case 0: return (x + y) % 2 == 0;
                case 1: return y % 2 == 0;
                case 2: return x % 3 == 0;
                case 3: return (x + y) % 3 == 0;
                case 4: return (Math.floor(x / 3) + Math.floor(y / 2)) % 2 == 0;
                case 5: return x * y % 2 + x * y % 3 == 0;
                case 6: return (x * y % 2 + x * y % 3) % 2 == 0;
                default: return ((x + y) % 2 + x * y % 3) % 2 == 0;
            }
        }
        function applymask(m) {
            for (var y = 0; y < size; y++) for (var x = 0; x < size; x++)
                if (!fun[y][x] && maskbit(m, x, y)) mods[y][x] = !mods[y][x];
        }
        function penalty() {
            var res = 0, x, y, dark = 0;
            function line(get) {
                var s = 0, run = 1, i, j, p;
                for (i = 1; i <= size; i++) {
                    if (i < size && get(i) == get(i - 1)) run++;
                    else { if (run >= 5) s += 3 + run - 5; run = 1; }
                }
                for (i = 0; i + 11 <= size; i++) {
                    p = 0;
                    for (j = 0; j < 11; j++) p = (p << 1) | (get(i + j) ? 1 : 0);
                    if (p == 0x5D0 || p == 0x05D) s += 40;
                }
                return s;
            }
            for (y = 0; y < size; y++) (function (yy) { res += line(function (i) { return mods[yy][i]; }); })(y);
            for (x = 0; x < size; x++) (function (xx) { res += line(function (i) { return mods[i][xx]; }); })(x);
            for (y = 0; y < size - 1; y++) for (x = 0; x < size - 1; x++)
                if (mods[y][x] == mods[y][x + 1] && mods[y][x] == mods[y + 1][x] && mods[y][x] == mods[y + 1][x + 1]) res += 3;
            for (y = 0; y < size; y++) for (x = 0; x < size; x++) if (mods[y][x]) dark++;
            res += (Math.ceil(Math.abs(dark * 20 - size * size * 10) / (size * size)) - 1) * 10;
            return res;
        }
        var best = 0, bestscore = Infinity;
        for (var m = 0; m < 8; m++) {
            applymask(m); fmt(m);
            var s = penalty();
            if (s < bestscore) { bestscore = s; best = m; }
            applymask(m);
        }
        applymask(best); fmt(best);
        var dim = size + 6, path = '';
        for (var yy = 0; yy < size; yy++) for (var xx = 0; xx < size; xx++)
            if (mods[yy][xx]) path += 'M' + (xx + 3) + ' ' + (yy + 3) + 'h1v1h-1z';
        return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ' + dim + ' ' + dim + '" shape-rendering="crispEdges" role="img" aria-label="QR"><rect width="100%" height="100%" fill="#ffffff"/><path d="' + path + '" fill="#111827"/></svg>';
    }

    // The one notice of the page from the template of the theme: it confirms or warns, offers Undo for opts.undo and stays opts.time ms
    function setToast(text, opts) {
        var tpl = document.querySelector('template[data-sl-toast]');
        var conf = opts || {};
        var time = conf.time || (conf.undo ? 10000 : 2600);
        var box, undo, shut;
        if (!text || !tpl) return;
        tpl.parentNode.querySelectorAll(':scope > :not(template)').forEach(function (one) { one.remove(); });
        box = tpl.content.firstElementChild.cloneNode(true);
        box.classList.toggle('sl-toast-warn', !!conf.warn);
        box.querySelector('[data-sl-toast-text]').textContent = text;
        box.querySelectorAll('[data-sl-toast-tone]').forEach(function (one) { one.hidden = one.getAttribute('data-sl-toast-tone') !== (conf.warn ? 'warn' : 'done'); });
        undo = box.querySelector('[data-sl-undo]');
        if (undo && conf.undo) {
            undo.hidden = false;
            undo.addEventListener('click', function () { setToastDrop(box); conf.undo(); });
        }
        shut = box.querySelector('[data-sl-toast-close]');
        if (shut) shut.addEventListener('click', function () { setToastDrop(box); });
        box.style.setProperty('--sl-d-toast', time + 'ms');
        tpl.parentNode.appendChild(box);
        window.setSoundPlay(conf.warn ? 'error' : 'success');
        window.requestAnimationFrame(function () { box.classList.add('sl-is-visible'); });
        box.sltimer = window.setTimeout(function () { setToastDrop(box); }, time);
    }
    window.setToast = setToast;

    // Take a notice off by the leaving motion of its theme and remove it once that has played
    function setToastDrop(box) {
        window.clearTimeout(box.sltimer);
        box.classList.remove('sl-is-visible');
        box.classList.add('sl-is-hiding');
        window.setTimeout(function () { box.remove(); }, isMotionStill() ? 0 : 400);
    }

    // Open a palette window, wired on its first opening; a row marked slOff is not offered, and run(row) follows once it has closed
    function setPaletteOpen(pal, run, recent) {
        var find = pal.querySelector('[role="combobox"]');
        pal.slRun = run;
        pal.slRecent = recent || '';
        pal.slPick = null;
        if (!pal.slWired) {
            pal.slWired = true;
            find.addEventListener('input', function () { setPaletteFilter(pal, find.value); });
            pal.addEventListener('keydown', setPaletteKeys);
            pal.addEventListener('close', function () {
                var row = pal.slPick;
                pal.slPick = null;
                if (row && pal.slRun) pal.slRun(row);
            });
            pal.addEventListener('click', function (ev) {
                var row = ev.target.closest ? ev.target.closest('[role="option"]') : null;
                if (!row) return;
                ev.preventDefault();
                setPaletteRun(pal, row);
            });
        }
        find.value = '';
        setPaletteFilter(pal, '');
        setWindowOpen(pal);
    }
    window.setPaletteOpen = setPaletteOpen;

    // The places of the typed letters inside a name, in the order they were typed, or null where one of them is missing
    function getPaletteHits(name, want) {
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
            if (run) node.appendChild(document.createTextNode(run));
            run = '';
            mark = document.createElement('b');
            mark.textContent = name.charAt(i);
            node.appendChild(mark);
        }
        if (run) node.appendChild(document.createTextNode(run));
    }

    // Keep the commands whose name holds the typed letters in their order, hide the groups left empty and the keys while a search runs
    function setPaletteFilter(pal, term) {
        var want = term.trim().toLowerCase();
        var first = null;
        var help = pal.querySelector('[data-sl-pal-help]');
        pal.querySelectorAll('[role="option"]').forEach(function (row) {
            var name = row.querySelector('span');
            var hits;
            if (row.slName === undefined) row.slName = name.textContent;
            hits = getPaletteHits(row.slName.toLowerCase(), want);
            row.hidden = !!row.slOff || !hits;
            setPaletteName(name, row.slName, hits || []);
            if (!row.hidden && !first) first = row;
        });
        pal.querySelectorAll('[role="group"]').forEach(function (group) { group.hidden = !group.querySelector('[role="option"]:not([hidden])'); });
        pal.querySelector('[data-sl-pal-empty]').hidden = !!first;
        if (help) help.hidden = want !== '';
        setPaletteActive(pal, first);
    }

    // Choose one command; the focus stays in the search, which names the chosen one to a screen reader
    function setPaletteActive(pal, row) {
        var find = pal.querySelector('[role="combobox"]');
        pal.querySelectorAll('[role="option"][aria-selected]').forEach(function (one) { one.removeAttribute('aria-selected'); });
        if (!row) {
            find.removeAttribute('aria-activedescendant');
            return;
        }
        row.setAttribute('aria-selected', 'true');
        find.setAttribute('aria-activedescendant', row.id);
        row.scrollIntoView({ block: 'nearest' });
    }

    // Step the chosen command up or down through the shown ones, round from the end to the start
    function setPaletteStep(pal, step) {
        var rows = Array.prototype.filter.call(pal.querySelectorAll('[role="option"]'), function (one) { return !one.hidden; });
        var now;
        if (!rows.length) return;
        now = rows.indexOf(pal.querySelector('[role="option"][aria-selected]'));
        setPaletteActive(pal, rows[(now + step + rows.length) % rows.length]);
    }

    // The keys of a palette: the arrows choose, Enter in the search runs, Escape closes at once over a typed search, Ctrl+K closes it again
    function setPaletteKeys(ev) {
        var pal = ev.currentTarget;
        if (ev.isComposing) return;
        if (ev.key === 'ArrowDown' || ev.key === 'ArrowUp') {
            ev.preventDefault();
            setPaletteStep(pal, ev.key === 'ArrowDown' ? 1 : -1);
            return;
        }
        if (ev.key === 'Enter' && ev.target.getAttribute('role') === 'combobox') {
            ev.preventDefault();
            setPaletteRun(pal, pal.querySelector('[role="option"][aria-selected]'));
            return;
        }
        if (ev.key !== 'Escape' && !((ev.ctrlKey || ev.metaKey) && ev.code === 'KeyK')) return;
        ev.preventDefault();
        setWindowClose(pal);
    }

    // Close the palette on the chosen command, a second press naming it again, and raise it in its group as the recent one
    function setPaletteRun(pal, row) {
        if (!row || row.hidden) return;
        pal.querySelectorAll('[data-recent]').forEach(function (one) { one.removeAttribute('data-recent'); });
        row.parentNode.insertBefore(row, row.parentNode.querySelector('[role="option"]'));
        row.setAttribute('data-recent', pal.slRecent);
        pal.slPick = row;
        setWindowClose(pal);
    }

    // Share endpoints: {u} = encoded canonical url, {t} = encoded title
    var sharenets = {
        telegram: 'https://t.me/share/url?url={u}&text={t}',
        whatsapp: 'https://wa.me/?text={t}%20{u}',
        viber: 'viber://forward?text={t}%20{u}',
        vk: 'https://vk.com/share.php?url={u}&title={t}',
        ok: 'https://connect.ok.ru/offer?url={u}&title={t}',
        facebook: 'https://www.facebook.com/sharer/sharer.php?u={u}',
        x: 'https://x.com/intent/post?url={u}&text={t}',
        pinterest: 'https://pinterest.com/pin/create/button/?url={u}&description={t}',
        reddit: 'https://www.reddit.com/submit?url={u}&title={t}',
        linkedin: 'https://www.linkedin.com/sharing/share-offsite/?url={u}',
        mail: 'mailto:?subject={t}&body={u}'
    };

    // Share helpers: canonical url and title come from the closest annotated host (dial or dialog)
    function getShareData(node) {
        var host = node.closest('[data-sl-share-url]');
        if (!host) return null;
        var canon = document.querySelector('link[rel="canonical"]');
        var base = canon ? canon.href : window.location.href.split('#')[0];
        var url = null;
        try {
            url = new URL(host.getAttribute('data-sl-share-url') || base, base);
        } catch (error) {
            return null;
        }
        if (url.protocol !== 'http:' && url.protocol !== 'https:') return null;
        return { url: url.href, title: host.getAttribute('data-sl-share-title') || document.title };
    }

    // Copy a text with a clipboard fallback, flash a check icon on the button and confirm with its toast: the address of a share, the source of a code block
    function setCopyText(node, text) {
        var done = function () {
            setToast(node.getAttribute('data-sl-done') || '');
            var icon = node.querySelector('.bi');
            if (!icon) return;
            var was = icon.className;
            icon.className = 'bi bi-check-lg';
            node.classList.add('sl-is-copied');
            window.setTimeout(function () { icon.className = was; node.classList.remove('sl-is-copied'); }, 1400);
        };
        if (navigator.clipboard && window.isSecureContext) { navigator.clipboard.writeText(text).then(done, done); return; }
        var area = document.createElement('textarea');
        area.value = text;
        area.style.position = 'fixed';
        area.style.opacity = '0';
        document.body.appendChild(area);
        area.select();
        try { document.execCommand('copy'); } catch (err) {}
        area.remove();
        done();
    }

    // A code block takes its buttons once the script is there: until then the whole code shows and nothing promises a copy
    function setCodeBlocks(root) {
        var list = root && root.querySelectorAll ? root.querySelectorAll('[data-sl-code]') : [];
        for (var i = 0; i < list.length; i++) list[i].setAttribute('data-ready', '');
    }

    // The source of a code block without its line numbers: the lines of a numbered table joined, the plain text otherwise
    function getCodeText(box) {
        var body = box.querySelector('[data-sl-code-body]');
        var rows = body ? body.querySelectorAll('.hljs-ln-code, [data-sl-code-line]') : [];
        if (rows.length) return Array.prototype.map.call(rows, function (one) { return one.textContent; }).join('\n');
        return body ? body.innerText : '';
    }

    // The copy button and the fold of a code block, one listener for every block the page has or receives
    function setCodeActions() {
        document.addEventListener('click', function (event) {
            var btn = event.target && event.target.closest ? event.target.closest('[data-sl-code-copy], [data-sl-code-more]') : null;
            var box = btn ? btn.closest('[data-sl-code]') : null;
            if (!box) return;
            if (btn.hasAttribute('data-sl-code-copy')) return setCopyText(btn, getCodeText(box));
            var open = !box.hasAttribute('data-open');
            var icon = btn.querySelector('.bi');
            var text = btn.querySelector('span');
            box.toggleAttribute('data-open', open);
            btn.setAttribute('aria-expanded', String(open));
            if (text) text.textContent = btn.getAttribute(open ? 'data-less' : 'data-more') || '';
            if (icon) icon.className = open ? 'bi bi-chevron-up' : 'bi bi-chevron-down';
            if (!open && box.getBoundingClientRect().top < 0) box.scrollIntoView({ block: 'start' });
        });
    }

    // Burst: one node pops, rings and throws sparks; a turn staggers it behind the nodes before it in a row
    function setBurst(node, turn) {
        var at = turn || 0;
        node.style.setProperty('--sl-d-turn', at);
        node.classList.add('sl-is-burst');
        window.setTimeout(function () {
            node.classList.remove('sl-is-burst');
            node.style.removeProperty('--sl-d-turn');
        }, 700 + at * 100);
    }

    // Favorites: remember the clicked star so the freshly swapped on-state can burst and toast
    var favpending = null;

    function setFavoriteBurst() {
        if (!favpending) return;
        var host = document.getElementById(favpending.id);
        var done = favpending.done;
        favpending = null;
        if (!host) return;
        var star = host.querySelector('.sl-fav-on');
        if (!star) return;
        setBurst(star);
        setToast(done);
    }

    // Live boxes: containers that own auto-refresh state (voting widget root or a marked htmx container)
    function getLiveBox(node) {
        return node.closest('[data-sl-vote-live], [data-sl-live-box]');
    }

    function setLiveState(box) {
        var chip = box.querySelector('.sl-live-chip');
        if (chip) chip.classList.toggle('sl-is-paused', box.hasAttribute('data-sl-live-paused'));
    }

    // Live chips: init refresh stamps and sync chip visuals after load and htmx swaps
    function setLiveChips(root) {
        var scope = root || document;
        var boxes = Array.prototype.slice.call(scope.querySelectorAll('[data-sl-vote-live], [data-sl-live-box]'));
        if (scope !== document && scope.matches && scope.matches('[data-sl-vote-live], [data-sl-live-box]')) boxes.push(scope);
        for (var i = 0; i < boxes.length; i++) {
            if (!boxes[i].hasAttribute('data-sl-live-stamp')) boxes[i].setAttribute('data-sl-live-stamp', Date.now());
            setLiveState(boxes[i]);
        }
    }

    // Live chips: tick the "updated N sec ago" counters once per second
    function setLiveTick() {
        var boxes = document.querySelectorAll('[data-sl-live-stamp]');
        for (var i = 0; i < boxes.length; i++) {
            if (boxes[i].hasAttribute('data-sl-live-paused')) continue;
            var ago = boxes[i].querySelector('[data-sl-live-ago]');
            if (!ago) continue;
            var sec = Math.max(0, Math.round((Date.now() - parseInt(boxes[i].getAttribute('data-sl-live-stamp'), 10)) / 1000));
            ago.textContent = sec + ' ' + (ago.getAttribute('data-sl-live-unit') || 's');
        }
    }

    // Delegated UI actions: share networks and dialogs, copy, favorite star capture, live pause toggle
    function setUiActions() {
        document.addEventListener('click', function (event) {
            var node = event.target;
            if (!node || !node.closest) return;
            var fav = node.closest('[data-sl-fav]');
            if (fav) favpending = { id: fav.getAttribute('data-sl-fav'), done: fav.getAttribute('data-sl-done') || '' };
            var call = node.closest('[data-sl-open]');
            if (call) {
                event.preventDefault();
                setWindowOpen(document.getElementById(call.getAttribute('data-sl-open')));
                return;
            }
            var close = node.closest('[data-sl-close]');
            if (close) {
                var dlg = close.closest('dialog');
                if (dlg) setWindowClose(dlg);
                return;
            }
            var wide = node.closest('[data-sl-full]');
            if (wide) {
                var room = wide.closest('dialog.sl-modal');
                if (room) { event.preventDefault(); setWindowExpand(room, wide); }
                return;
            }
            // A press past the frame arrives at the dialog itself; a window that must be answered does not listen to it
            if (node.tagName === 'DIALOG' && node.classList.contains('sl-modal')) {
                if (!node.hasAttribute('data-sl-static')) setWindowClose(node);
                return;
            }
            var okay = node.closest('[data-sl-confirm-ok]');
            if (okay) {
                var box = okay.closest('dialog');
                var ask = box ? box.slask : null;
                var run = box ? box.slrun : null;
                if (box) {
                    setWindowClose(box);
                    box.slrun = null;
                }
                if (typeof run === 'function') {
                    run();
                    return;
                }
                if (ask && ask.href) { window.location.href = ask.href; return; }
                if (ask) {
                    // A submit button may own its form through the form attribute, which puts the form outside its ancestors
                    var askform = ask.form || ask.closest('form');
                    // requestSubmit and not submit: the latter skips every submit listener, and a field a listener fills in would travel empty or stale
                    if (askform) askform.requestSubmit ? askform.requestSubmit() : askform.submit();
                }
                return;
            }
            var reply = node.closest('[data-sl-reply]');
            if (reply) {
                setCommentTarget(reply.getAttribute('data-sl-reply') || '');
                return;
            }
            var toggle = node.closest('[data-sl-live-toggle]');
            if (toggle) {
                var live = getLiveBox(toggle);
                if (!live) return;
                if (live.hasAttribute('data-sl-live-paused')) live.removeAttribute('data-sl-live-paused');
                else live.setAttribute('data-sl-live-paused', '1');
                setLiveState(live);
                return;
            }
            var net = node.closest('[data-sl-net]');
            if (net) {
                var data = getShareData(net);
                var key = net.getAttribute('data-sl-net');
                var mask = sharenets[key] || '';
                if (!data || !mask) return;
                var target = mask.replace('{u}', encodeURIComponent(data.url)).replace('{t}', encodeURIComponent(data.title));
                var sheet = net.closest('dialog');
                if (sheet) setWindowClose(sheet);
                if (key === 'mail' || key === 'viber') { window.location.href = target; return; }
                window.open(target, 'slshare', 'width=640,height=520,noopener');
                return;
            }
            var act = node.closest('[data-sl-share-act]');
            if (!act) return;
            var kind = act.getAttribute('data-sl-share-act');
            var share = getShareData(act);
            if (kind === 'copy' && share) setCopyText(act, share.url);
            if (kind === 'qr' && share) {
                var qr = document.getElementById('sl-share-qr');
                if (!qr) return;
                qr.setAttribute('data-sl-share-url', share.url);
                var note = qr.querySelector('.sl-modal-note');
                if (note) note.textContent = share.url;
                var slot = qr.querySelector('[data-sl-qr]');
                if (slot && slot.getAttribute('data-sl-qr') !== share.url) {
                    slot.innerHTML = getQrSvg(share.url);
                    slot.setAttribute('data-sl-qr', share.url);
                }
                setWindowOpen(qr);
            }
            if (kind === 'more' && share) {
                var more = document.getElementById('sl-share-sheet');
                if (!more) return;
                more.setAttribute('data-sl-share-url', share.url);
                more.setAttribute('data-sl-share-title', share.title);
                setWindowOpen(more);
            }
        });
        // A request htmx fires on its own clock is a poll, which the panel session does not count as an action
        document.addEventListener('htmx:configRequest', function (event) {
            var elt = event.detail.elt;
            if (event.detail.triggeringEvent || !elt || !/\bevery\b/.test(elt.getAttribute('hx-trigger') || '')) return;
            event.detail.headers['X-SL-Poll'] = '1';
        });
        // Paused live boxes swallow their htmx polls, and a window that is shut or out of sight swallows its timed polls; direct clicks keep working
        document.addEventListener('htmx:beforeRequest', function (event) {
            var trig = event.detail && event.detail.requestConfig ? event.detail.requestConfig.triggeringEvent : null;
            if (trig && trig.type === 'click') return;
            var elt = event.detail ? event.detail.elt : null;
            var win = elt && elt.closest ? elt.closest('dialog') : null;
            var poll = !trig && !!win && /\bevery\b/.test(elt.getAttribute('hx-trigger') || '');
            if (elt && elt.closest && elt.closest('[data-sl-live-paused]')) event.preventDefault();
            else if (poll && (!win.open || document.hidden)) event.preventDefault();
        });
        // A target that carries aria-busy tells the reader while its own request runs, as the summary of the admin block does
        document.addEventListener('htmx:beforeRequest', function (event) {
            var box = event.detail ? event.detail.target : null;
            if (!event.defaultPrevented && box && box.hasAttribute && box.hasAttribute('aria-busy')) box.setAttribute('aria-busy', 'true');
        });
        document.addEventListener('htmx:afterRequest', function (event) {
            var box = event.detail ? event.detail.target : null;
            if (box && box.hasAttribute && box.hasAttribute('aria-busy')) box.setAttribute('aria-busy', 'false');
        });
        window.setInterval(function () {
            if (document.hidden || isMotionStill()) return;
            var lists = document.querySelectorAll('[data-sl-live-scroll].sl-is-open');
            for (var i = 0; i < lists.length; i++) {
                var list = lists[i];
                if (list.closest('[data-sl-live-paused]') || list.matches(':hover') || list.contains(document.activeElement)) continue;
                var limit = list.scrollHeight - list.clientHeight;
                if (limit <= 1) continue;
                var wait = parseInt(list.getAttribute('data-sl-scroll-wait') || '0', 10);
                if (list.scrollTop >= limit - 1) {
                    if (!wait) list.setAttribute('data-sl-scroll-wait', Date.now() + 1000);
                    else if (Date.now() >= wait) {
                        list.scrollTop = 0;
                        list.removeAttribute('data-sl-scroll-wait');
                    }
                    continue;
                }
                list.scrollTop += 1;
            }
        }, 50);
        window.setInterval(setLiveTick, 1000);
    }

    // The idempotency key is minted here rather than rendered by PHP, so every attempt from one rendered form gets a value of its own
    function setCommentKeys(root) {
        var list = (root && root.querySelectorAll) ? root.querySelectorAll('[data-sl-reqkey]') : [];
        for (var i = 0; i < list.length; i++) {
            if (!list[i].value) list[i].value = getRequestKey();
        }
    }

    // One delivery key: 16 random bytes as 32 lowercase hex characters
    function getRequestKey() {
        var raw = new Uint8Array(16);
        if (window.crypto && window.crypto.getRandomValues) {
            window.crypto.getRandomValues(raw);
        } else {
            for (var r = 0; r < raw.length; r++) raw[r] = Math.floor(Math.random() * 256);
        }
        var key = '';
        for (var j = 0; j < raw.length; j++) key += ('0' + raw[j].toString(16)).slice(-2);
        return key;
    }

    // A vote key stays on the control while the outcome is unknown, so a repeat of the click answers the stored vote instead of placing a second one
    var ratepending = null;

    function setRatingBurst(box) {
        if (!ratepending || !box || box.id !== ratepending.id) return;
        var rate = ratepending.rate;
        var thumb = ratepending.thumb;
        ratepending = null;
        if (thumb) {
            var hand = box.querySelector(rate === 5 ? '.sl-rate-plus' : '.sl-rate-minus');
            if (hand) setBurst(hand);
            return;
        }
        var stars = box.querySelectorAll('.sl-urating .sl-star');
        for (var i = 0; i < Math.min(rate, stars.length); i++) setBurst(stars[i], i);
    }

    function setRatingVotes() {
        document.addEventListener('htmx:configRequest', function (event) {
            var elt = event.detail ? event.detail.elt : null;
            if (!elt || !elt.closest || !elt.closest('[data-sl-rate]')) return;
            if (!elt.getAttribute('data-sl-rate-key')) elt.setAttribute('data-sl-rate-key', getRequestKey());
            event.detail.parameters.request = elt.getAttribute('data-sl-rate-key');
            var host = elt.closest('[data-sl-rate]');
            var rate = parseInt(event.detail.parameters.rate, 10);
            var thumb = elt.classList.contains('sl-rate-plus') || elt.classList.contains('sl-rate-minus');
            if (rate > 0 && (thumb || elt.classList.contains('sl-star'))) {
                ratepending = { id: (host.getAttribute('hx-target') || '').replace(/^#/, ''), rate: rate, thumb: thumb };
            }
        });
        document.addEventListener('htmx:afterSwap', function (event) {
            setRatingBurst(event.target);
        });
        document.addEventListener('htmx:afterRequest', function (event) {
            var elt = event.detail ? event.detail.elt : null;
            var xhr = event.detail ? event.detail.xhr : null;
            if (!elt || !xhr || !elt.getAttribute || !elt.getAttribute('data-sl-rate-key')) return;
            if (xhr.status > 0 && xhr.status < 500) elt.removeAttribute('data-sl-rate-key');
            if (xhr.status >= 400) ratepending = null;
            if (xhr.status < 400) return;
            var box = new DOMParser().parseFromString(xhr.responseText || '', 'text/html').body;
            var line = box.querySelector('.sl-alert-text') || box;
            setToast((line.textContent || '').replace(/\s+/g, ' ').trim().slice(0, 200) || String(xhr.status), { warn: true });
        });
    }

    // Quick edit takes its keys only while nothing stands in front of the editor: a window, the confirm dialog, the fullscreen and an editor popup come first
    function getQuickOp(event) {
        var conf = event.detail ? event.detail.requestConfig : null;
        var path = conf ? String(conf.path || '') : '';
        if (path.indexOf('op=getQuickEdit') >= 0) return 'open';
        return path.indexOf('op=updateQuickEdit') >= 0 ? 'save' : '';
    }

    function isQuickCovered() {
        if (document.querySelector('dialog.sl-modal[open], .sl-toastui-editor-fullscreen, [data-sl-editor][data-full]')) return true;
        return Array.prototype.some.call(document.querySelectorAll('.toastui-editor-popup, .ck-balloon-panel_visible, .tox-dialog, .tox-menu'), function (one) {
            return one.offsetParent !== null;
        });
    }

    function setQuickBack(region) {
        if (!region) return;
        window.SlaedEditors.drop(region);
        region.innerHTML = region.slkeep || '';
        region.slkeep = null;
        if (window.htmx) window.htmx.process(region);
    }

    function setQuickCancel(form) {
        var region = form.closest('[data-sl-quick]');
        if (!form.hasAttribute('data-sl-quick-dirty')) {
            setQuickBack(region);
            return;
        }
        window.setConfirmTask(form.getAttribute('data-sl-quick-leave') || '', function () { setQuickBack(region); });
    }

    function setQuickEdit() {
        document.addEventListener('htmx:beforeRequest', function (event) {
            var region = event.detail ? event.detail.target : null;
            if (getQuickOp(event) === 'open' && region && region.querySelector('[data-sl-quick-form]')) event.preventDefault();
        });
        document.addEventListener('htmx:beforeSwap', function (event) {
            var region = event.detail ? event.detail.target : null;
            var step = getQuickOp(event);
            if (!step || !region || !event.detail.shouldSwap || !region.hasAttribute('data-sl-quick')) return;
            if (step === 'open') region.slkeep = region.innerHTML;
            else window.SlaedEditors.drop(region);
        });
        document.addEventListener('htmx:afterRequest', function (event) {
            var xhr = event.detail ? event.detail.xhr : null;
            var step = getQuickOp(event);
            if (!step || !xhr || xhr.status < 400) return;
            var box = new DOMParser().parseFromString(xhr.responseText || '', 'text/html').body;
            var form = event.detail.elt && event.detail.elt.closest ? event.detail.elt.closest('[data-sl-quick-form]') : null;
            var now = box.querySelector('[data-sl-quick-stamp]');
            if (xhr.status === 409 && form && now) {
                form.querySelector('[name="stamp"]').value = now.getAttribute('data-sl-quick-stamp');
                form.closest('[data-sl-quick]').slkeep = now.innerHTML;
                window.setConfirmTask(form.getAttribute('data-sl-quick-ask') || '', function () { form.requestSubmit(); });
                return;
            }
            var line = box.querySelector('.sl-alert-text') || box;
            setToast((line.textContent || '').replace(/\s+/g, ' ').trim().slice(0, 200) || String(xhr.status), { warn: true });
        });
        document.addEventListener('sl-quick-note', function (event) {
            setToast(event.detail ? String(event.detail.value || '') : '', { warn: true });
        });
        ['input', 'change'].forEach(function (name) {
            document.addEventListener(name, function (event) {
                var form = event.target && event.target.closest ? event.target.closest('[data-sl-quick-form]') : null;
                if (form) form.setAttribute('data-sl-quick-dirty', '1');
            });
        });
        document.addEventListener('click', function (event) {
            var back = event.target && event.target.closest ? event.target.closest('[data-sl-quick-cancel]') : null;
            var form = back ? back.closest('[data-sl-quick-form]') : null;
            if (!form) return;
            event.preventDefault();
            setQuickCancel(form);
        });
        document.addEventListener('keydown', function (event) {
            var form = event.target && event.target.closest ? event.target.closest('[data-sl-quick-form]') : null;
            if (!form || event.defaultPrevented || isQuickCovered()) return;
            if (event.key === 'Escape') {
                event.preventDefault();
                setQuickCancel(form);
            } else if (event.key === 'Enter' && (event.ctrlKey || event.metaKey)) {
                event.preventDefault();
                form.requestSubmit();
            }
        });
    }

    // Replying points the submit form at one comment: the same action taken twice takes the reply back to the top level, so a reader can always undo the choice
    function setCommentTarget(id) {
        var form = document.getElementById('formcsave');
        var field = form ? form.querySelector('[data-sl-reply-to]') : null;
        if (!field) return;
        var same = field.value === id;
        field.value = same ? '' : id;
        var marked = document.querySelectorAll('.sl-com-reply-at');
        for (var i = 0; i < marked.length; i++) marked[i].classList.remove('sl-com-reply-at');
        if (!same) {
            var host = document.querySelector('[id="' + id + '"]');
            if (host) host.classList.add('sl-com-reply-at');
        }
        var text = form.querySelector('[name="text"]');
        if (text && text.focus) text.focus();
    }

    // An address ending in #reply-ID opens the page in the answer to that comment, as its reply button would; a comment that takes no reply is only brought into view
    function setCommentHash() {
        var hit = /^#reply-(\d+)$/.exec(window.location.hash);
        var form = document.getElementById('formcsave');
        var host = hit ? document.getElementById(hit[1]) : null;
        if (!hit) return;
        if (form && document.querySelector('[data-sl-reply="' + hit[1] + '"]')) {
            setCommentTarget(hit[1]);
            form.scrollIntoView({ block: 'center', behavior: 'instant' });
        } else if (host) {
            host.scrollIntoView({ block: 'center', behavior: 'instant' });
        }
    }
    window.addEventListener('hashchange', setCommentHash);

    // Only a stored comment clears the form: a refused submit keeps the text the visitor typed, the reply target it named and the key it was refused with
    document.addEventListener('sl-comment-add', function () {
        var form = document.getElementById('formcsave');
        if (!form) return;
        form.reset();
        var area = form.querySelector('textarea[name="text"]');
        var api = window.SlaedToastUi;
        var edit = (area && api && typeof api.getEditor === 'function') ? api.getEditor(area.id) : null;
        if (edit && typeof edit.setMarkdown === 'function') edit.setMarkdown('');
        var key = form.querySelector('[data-sl-reqkey]');
        if (key) key.value = '';
        var marked = document.querySelectorAll('.sl-com-reply-at');
        for (var i = 0; i < marked.length; i++) marked[i].classList.remove('sl-com-reply-at');
        setCommentKeys(form);
    });

    // The focus follows the pane switch only where it is visible: on a wide layout both panels stay on screen and moving the focus would jump the page
    function setPrivatPane(name) {
        var split = document.getElementById('prpage');
        if (!split) return;
        split.setAttribute('data-sl-pane', name);
        var page = split.parentNode;
        if (!page || page.clientWidth > 800) return;
        var box = document.getElementById(name === 'view' ? 'prview' : 'prlist');
        if (!box) return;
        box.setAttribute('tabindex', '-1');
        box.focus();
    }

    // The editor is filled through its own API: writing the hidden textarea changes what the form sends but not what the writer sees
    function setPrivatCarry(root) {
        var carry = root && root.querySelector ? root.querySelector('[data-sl-pm-carry]') : null;
        if (!carry) return;
        var form = document.getElementById('prform');
        if (form) {
            var to = form.querySelector('[name="name"]');
            var head = form.querySelector('[name="title"]');
            if (to) {
                var was = to.value;
                to.value = carry.getAttribute('data-sl-pm-name') || '';
                if (to.value !== was) to.dispatchEvent(new Event('input', { bubbles: true }));
            }
            if (head) head.value = carry.getAttribute('data-sl-pm-title') || '';
            var area = form.querySelector('textarea[name="text"]');
            var api = window.SlaedToastUi;
            var edit = (area && api && typeof api.getEditor === 'function') ? api.getEditor(area.id) : null;
            if (edit && typeof edit.setMarkdown === 'function') {
                edit.setMarkdown(carry.value);
                syncEditorValue(area.id, edit);
            } else if (area) {
                area.value = carry.value;
                area.dispatchEvent(new Event('input', { bubbles: true }));
            }
        }
        if (carry.parentNode) carry.parentNode.removeChild(carry);
    }

    // Clearing the form goes through the editor API and the recipient through an input event, so the card beside the field leaves with its name
    function setPrivatWipe() {
        var form = document.getElementById('prform');
        if (!form) return;
        var to = form.querySelector('[name="name"]');
        var head = form.querySelector('[name="title"]');
        var area = form.querySelector('textarea[name="text"]');
        var api = window.SlaedToastUi;
        var edit = (area && api && typeof api.getEditor === 'function') ? api.getEditor(area.id) : null;
        if (head) head.value = '';
        if (edit && typeof edit.setMarkdown === 'function') {
            edit.setMarkdown('');
            syncEditorValue(area.id, edit);
        } else if (area) {
            area.value = '';
            area.dispatchEvent(new Event('input', { bubbles: true }));
        }
        if (!to) return;
        to.value = '';
        to.dispatchEvent(new Event('input', { bubbles: true }));
    }

    // The carrier fills the editor after the swap, so focusing it before the answer arrives costs nothing and saves the reader the scroll
    function setPrivatReply() {
        var form = document.getElementById('prform');
        var area = form ? form.querySelector('textarea[name="text"]') : null;
        var api = window.SlaedToastUi;
        var edit = (area && api && typeof api.getEditor === 'function') ? api.getEditor(area.id) : null;
        if (edit && typeof edit.focus === 'function') edit.focus();
        else if (form && form.scrollIntoView) form.scrollIntoView({ block: 'nearest' });
    }

    // One row at a time carries the open mark: the answer that opened a message redraws only that row, so the row it replaced has to be cleared here
    function setPrivatOpen(item) {
        var marked = document.querySelectorAll('.sl-pmf-item[aria-current]');
        for (var i = 0; i < marked.length; i++) marked[i].removeAttribute('aria-current');
        item.setAttribute('aria-current', 'true');
    }

    // A filter chip is always on the page and only changes state, or a filter once taken off could never be put back; the chip numbers stay as drawn
    function setPrivatChip(chip) {
        var form = document.getElementById('prtools');
        if (!form) return;
        var group = chip.getAttribute('data-sl-pm-chip');
        var value = chip.getAttribute('data-sl-pm-value') || '';
        var names = group === 'all' ? ['stat', 'perd'] : [group];
        for (var i = 0; i < names.length; i++) {
            var field = form.querySelector('[name="' + names[i] + '"]');
            if (!field) continue;
            field.value = (group === 'all' || field.value === value) ? '' : value;
        }
        setPrivatChipState(form);
        form.requestSubmit();
    }

    // What every chip of the bar reads back off the hidden fields, so the pressed states and the request can never tell two different stories
    function setPrivatChipState(form) {
        var bar = document.getElementById('prchips');
        if (!bar) return;
        var chips = bar.querySelectorAll('[data-sl-pm-chip]');
        var live = false;
        for (var i = 0; i < chips.length; i++) {
            var group = chips[i].getAttribute('data-sl-pm-chip');
            if (group === 'all') continue;
            var field = form.querySelector('[name="' + group + '"]');
            var on = !!field && field.value !== '' && field.value === chips[i].getAttribute('data-sl-pm-value');
            chips[i].setAttribute('aria-pressed', String(on));
            if (on) live = true;
        }
        var all = bar.querySelector('[data-sl-pm-chip="all"]');
        if (all) all.setAttribute('aria-pressed', String(!live));
    }

    document.addEventListener('click', function (event) {
        if (!event.target || !event.target.closest) return;
        var chip = event.target.closest('[data-sl-pm-chip]');
        if (chip) {
            setPrivatChip(chip);
            return;
        }
        if (event.target.closest('[data-sl-pm-clear]')) {
            var tools = document.getElementById('prtools');
            if (!tools) return;
            tools.querySelector('[name="stat"]').value = '';
            tools.querySelector('[name="perd"]').value = '';
            tools.querySelector('[name="query"]').value = '';
            setPrivatChipState(tools);
            tools.requestSubmit();
            return;
        }
        if (event.target.closest('[data-sl-pm-wipe]')) {
            setPrivatWipe();
            return;
        }
        if (event.target.closest('[data-sl-pm-back]')) {
            setPrivatPane('list');
            return;
        }
        // A reply from the deck opens the message and moves to the form in one click, so this no longer ends the handler: the element it sits on may be an opening one too
        if (event.target.closest('[data-sl-pm-reply]')) setPrivatReply();
        var open = event.target.closest('[hx-target="#prview"]');
        if (!open) return;
        var item = open.closest('.sl-pmf-item');
        if (item) setPrivatOpen(item);
    });

    // Э20 A date of a panel list or of the admin card of the floating menu reads relative in the language of the page; the exact moment stays in its title
    function setTimeAgo(node) {
        var list = (node && node.querySelectorAll ? node : document).querySelectorAll('time[data-sl-ago]');
        if (!list.length || !window.Intl || !window.Intl.RelativeTimeFormat) return;
        var fmt = new window.Intl.RelativeTimeFormat(document.documentElement.lang || undefined, { numeric: 'auto' });
        var steps = [['year', 31536000], ['month', 2592000], ['week', 604800], ['day', 86400], ['hour', 3600], ['minute', 60]];
        list.forEach(function (time) {
            var sec = (Date.parse(time.dateTime) - Date.now()) / 1000;
            var step = steps.find(function (one) { return Math.abs(sec) >= one[1]; }) || steps[steps.length - 1];
            if (isNaN(sec)) return;
            if (!time.title) time.title = time.textContent.trim();
            time.textContent = fmt.format(Math.round(sec / step[1]), step[0]);
        });
    }
    function setSlaedUi() {
        setWindowKeys();
        setTableSort(document);
        setLightbox();
        setImageReplace();
        setTableCheckAll();
        setToggleBlocks();
        setFloating(document);
        setFloatOutsideClose();
        setEditorInsertHandler();
        setDialToggle();
        setTabs(document);
        setFieldShows(document);
        setRepeatRows(document);
        setAlerts(document);
        setVoteBlocks(document);
        setUiActions();
        setLiveChips(document);
        setProfileScrolls(document);
        setSectionSpy(document);
        setKnobs(document);
        setStickyBars(document);
        setLensKeys();
        setHintKeys();
        setPagerKeys();
        setFillMeters();
        setTimeAgo(document);
        setDirtyForms(document);
        setDirtyLeave();
        setCodeBlocks(document);
        setCodeActions();
        setCommentKeys(document);
        setCommentHash();
        setRatingVotes();
        setQuickEdit();
        setPrivatCarry(document);
        setPrivatPoll();
        setWindowBack();
    }

    // Feed lists inside inactive tabs have zero height: re-measure pending ones right after any tab switch
    document.addEventListener('click', function (event) {
        if (!event.target || !event.target.closest || !event.target.closest('[data-sl-tab-link]')) return;
        window.requestAnimationFrame(function () { setProfileScrolls(document); });
    });

    document.addEventListener('htmx:afterSwap', function (event) {
        setTableSort(event.target);
        setToggleBlocks();
        setFloating(event.target);
        setTabs(event.target);
        setFieldShows(event.target);
        setRepeatRows(event.target);
        setDirtyForms(event.target);
        setAlerts(event.target);
        setVoteBlocks(event.target);
        setTimeAgo(event.target);
        var live = event.target && event.target.closest ? event.target.closest('[data-sl-live-box]') : null;
        if (live) live.setAttribute('data-sl-live-stamp', Date.now());
        setLiveChips(event.target);
        setProfileScrolls(document);
        setCommentKeys(event.target);
        setCodeBlocks(event.target);
        setKnobs(event.target && event.target.isConnected ? event.target : document);
        setStickyBars(event.target && event.target.isConnected ? event.target : document);
        setSwapReveal(event.target);
        setHintList(event.detail && event.detail.target);
        if (event.target && event.target.id === 'prview') {
            setPrivatPane('view');
            setPrivatCarry(event.target);
        }
        if (live === event.target && window.htmx) {
            var detail = live.querySelector('[data-sl-toggle-control][aria-expanded="true"]');
            var rows = live.querySelector('.sl-session-list [id$="-rows"]');
            var query = detail ? detail.getAttribute('hx-get') : '';
            if (detail && rows && query) window.htmx.ajax('GET', query, { source: detail, target: rows, swap: 'innerHTML' });
        }
        setFavoriteBurst();
        setSwapDone(event);
    });
    // Back or Forward redraws the history element without the swap events, so its parts are set up and settled as after any swap
    document.addEventListener('htmx:historyRestore', function () {
        var box = document.querySelector('[hx-history-elt]');
        if (!box) return;
        ['htmx:afterSwap', 'htmx:afterSettle'].forEach(function (name) {
            box.dispatchEvent(new CustomEvent(name, { bubbles: true, detail: { target: box, elt: box } }));
        });
    });

    // A request whose source carries the done text confirms its swap with the toast; the favorite star has its own moment, after the burst
    function setSwapDone(event) {
        var conf = event.detail ? event.detail.requestConfig : null;
        var from = conf && conf.elt && conf.elt.getAttribute ? conf.elt : null;
        if (!from || from.closest('[data-sl-fav]')) return;
        var done = from.getAttribute('data-sl-done') || '';
        if (done !== '') setToast(done);
    }

    // Answer 14: a member's unread messages are asked once a minute, on a hidden tab too; a grown number is heard and counted on the badges
    function setPrivatPoll() {
        var url = document.documentElement.getAttribute('data-sl-pm-poll');
        var mark = document.querySelector('[data-sl-pm-num]');
        var seen = mark ? Number(mark.textContent) || 0 : -1;
        if (!url) return;
        window.setInterval(function () {
            fetch(url, { credentials: 'same-origin', headers: { 'Accept': 'application/json', 'X-SL-Poll': '1' } })
                .then(function (res) { return res.ok ? res.json() : null; })
                .then(function (data) {
                    var num = data ? Number(data.num) : NaN;
                    var nav = document.getElementById('pmbadgenav');
                    if (isNaN(num)) return;
                    if (seen >= 0 && num > seen) window.setSoundPlay('inbox');
                    seen = num;
                    document.querySelectorAll('[data-sl-pm-num]').forEach(function (node) {
                        node.textContent = num;
                        node.classList.toggle('sl-pm-new', num > 0);
                    });
                    if (!nav) return;
                    nav.textContent = num > 99 ? '99+' : String(num);
                    nav.classList.toggle('sl-hidden', !num);
                })
                .catch(function () {});
        }, 60000);
    }

    // Motion is welcome unless the reader asked for less of it or the options stilled the page; theme scripts and plugins read one answer
    function isMotionStill() {
        return document.documentElement.hasAttribute('data-sl-still') || window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    }
    window.isMotionStill = isMotionStill;

    // The pointer light is written once a frame and only where motion is welcome; the theme decides whether body[data-sl-pointer] shows it
    var pointer = { left: 0, top: 0, frame: 0 };
    document.addEventListener('pointermove', function (event) {
        if (event.pointerType === 'touch' || isMotionStill()) return;
        pointer.left = event.clientX;
        pointer.top = event.clientY;
        if (pointer.frame) return;
        pointer.frame = window.requestAnimationFrame(function () {
            document.body.style.setProperty('--sl-d-pointer-x', pointer.left + 'px');
            document.body.style.setProperty('--sl-d-pointer-y', pointer.top + 'px');
            document.body.setAttribute('data-sl-pointer', '');
            pointer.frame = 0;
        });
    }, { passive: true });
    function setPointerOff() {
        window.cancelAnimationFrame(pointer.frame);
        pointer.frame = 0;
        document.body.removeAttribute('data-sl-pointer');
    }
    document.documentElement.addEventListener('pointerleave', setPointerOff);
    document.addEventListener('pointercancel', setPointerOff);
    window.addEventListener('blur', setPointerOff);

    window.addEventListener('resize', refitFloating);
    window.addEventListener('scroll', refitFloating, true);

    // A deferred bundle runs this file before the scripts that follow it, Tablesort among them, so the tables wait for the end of the bundle
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', setSlaedUi);
    } else {
        setSlaedUi();
        if (typeof window.Tablesort === 'undefined') document.addEventListener('DOMContentLoaded', function () { setTableSort(document); });
    }
})();
