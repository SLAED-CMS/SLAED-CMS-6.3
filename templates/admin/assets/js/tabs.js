function setAdminTabs(group) {
    var root = document.getElementById(group);
    if (!root) return;
    var list = Array.prototype.slice.call(root.querySelectorAll('a[rel]'));
    if (!list.length) return;
    var save = 'slaed-admin-tabs:'+group;
    var pick = function (link) {
        var rel = link.getAttribute('rel');
        if (!rel) return;
        for (var i = 0; i < list.length; i++) {
            var item = list[i];
            var show = item === link;
            var pane = document.getElementById(item.getAttribute('rel'));
            item.className = show ? 'selected' : '';
            item.setAttribute('aria-selected', show ? 'true' : 'false');
            item.setAttribute('tabindex', show ? '0' : '-1');
            if (pane) {
                pane.style.display = show ? 'block' : 'none';
                pane.hidden = !show;
                pane.setAttribute('aria-hidden', show ? 'false' : 'true');
            }
        }
        try {
            window.sessionStorage.setItem(save, String(list.indexOf(link)));
        } catch (err) {
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
        var pane = document.getElementById(list[i].getAttribute('rel'));
        if (pane) pane.setAttribute('role', 'tabpanel');
    }
    var idx = -1;
    try {
        idx = parseInt(window.sessionStorage.getItem(save), 10);
    } catch (err) {
    }
    if (isNaN(idx) || !list[idx]) idx = list.findIndex(function (item) { return item.className === 'selected'; });
    if (idx < 0) idx = 0;
    pick(list[idx]);
}
