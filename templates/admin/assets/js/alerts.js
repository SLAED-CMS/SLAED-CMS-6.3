function initAdminAlerts(scope) {
    var root = scope || document;
    var list = root.querySelectorAll ? root.querySelectorAll('[data-sl-autohide]') : [];
    for (var i = 0; i < list.length; i++) {
        var node = list[i];
        if (node.getAttribute('data-sl-alert-ready') === '1') continue;
        node.setAttribute('data-sl-alert-ready', '1');
        var time = parseInt(node.getAttribute('data-sl-autohide'), 10);
        if (isNaN(time) || time < 1) time = 15000;
        window.setTimeout((function (item) {
            return function () {
                if (!item || !item.parentNode) return;
                item.classList.add('sl-is-hiding');
                window.setTimeout(function () {
                    if (item && item.parentNode) item.parentNode.removeChild(item);
                }, 400);
            };
        })(node), time);
    }
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function () {
        initAdminAlerts(document);
    });
} else {
    initAdminAlerts(document);
}

document.addEventListener('htmx:load', function (event) {
    initAdminAlerts(event.target || document);
});
