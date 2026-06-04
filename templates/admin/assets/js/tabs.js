function setAdminTabs(group) {
    var root = typeof group === 'string' ? document.getElementById(group) : group;
    if (!root) return;
    var list = Array.prototype.slice.call(root.querySelectorAll('[data-sl-tab-link][data-sl-tab-target]'));
    if (!list.length) return;
    var saveGroup = root.getAttribute('data-sl-tabs-init') || root.id || 'tabs';
    var save = 'slaed-admin-tabs:'+saveGroup;
    var syncSelector = root.getAttribute('data-sl-tabs-sync');
    var pick = function (link) {
        var index = list.indexOf(link);
        var rel = link.getAttribute('data-sl-tab-target');
        if (!rel) return;
        for (var i = 0; i < list.length; i++) {
            var item = list[i];
            var show = item === link;
            var target = item.getAttribute('data-sl-tab-target');
            var pane = root.querySelector('[data-sl-tab-panel="' + target + '"]') || document.getElementById(target);
            if (item.classList) {
                item.classList.toggle('selected', show);
                item.classList.toggle('sl-is-active', show);
            } else {
                item.className = show ? 'selected sl-is-active' : '';
            }
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
            shows[s].style.display = (shows[s].getAttribute('data-sl-tab-show') === String(index)) ? '' : 'none';
        }
        try {
            window.sessionStorage.setItem(save, String(index));
        } catch (err) {
        }
        if (syncSelector) {
            var syncNodes = document.querySelectorAll(syncSelector);
            for (var k = 0; k < syncNodes.length; k++) {
                if ('value' in syncNodes[k]) syncNodes[k].value = String(index);
            }
        }
        var infos = document.querySelectorAll('[data-sl-tab-info-link="' + saveGroup + '"]');
        for (var j = 0; j < infos.length; j++) {
            var info = infos[j];
            try {
                var url = new URL(info.href, window.location.href);
                url.searchParams.set('tab', String(index));
                info.href = url.toString();
            } catch (err) {
            }
        }
    };
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
        var pane = root.querySelector('[data-sl-tab-panel="' + target + '"]') || document.getElementById(target);
        if (pane) pane.setAttribute('role', 'tabpanel');
    }
    var idx = -1;
    var attr = parseInt(root.getAttribute('data-sl-tabs-index'), 10);
    if (!isNaN(attr) && list[attr]) idx = attr;
    try {
        if (idx < 0) idx = parseInt(window.sessionStorage.getItem(save), 10);
    } catch (err) {
    }
    if (isNaN(idx) || !list[idx]) idx = list.findIndex(function (item) {
        return item.classList
            ? item.classList.contains('sl-is-active') || item.classList.contains('selected')
            : item.className === 'selected' || item.className === 'selected sl-is-active';
    });
    if (idx < 0) idx = 0;
    pick(list[idx]);
}

function initAdminTabs(scope) {
    var root = scope || document;
    var nodes = root.querySelectorAll ? root.querySelectorAll('[data-sl-tabs-init]') : [];
    for (var i = 0; i < nodes.length; i++) {
        var node = nodes[i];
        if (node.getAttribute('data-sl-tabs-ready') === '1') continue;
        setAdminTabs(node);
        node.setAttribute('data-sl-tabs-ready', '1');
    }
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function () {
        initAdminTabs(document);
    });
} else {
    initAdminTabs(document);
}

document.addEventListener('htmx:load', function (event) {
    initAdminTabs(event.target || document);
});
