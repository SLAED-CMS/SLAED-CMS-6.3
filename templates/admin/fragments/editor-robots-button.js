(function () {
    function applyRobotsTemplate(button) {
        var src = button.getAttribute('data-sl-robots-template');
        var targetId = button.getAttribute('data-sl-robots-target') || 'code';
        var area = document.getElementById(targetId);
        if (!area || !src) return;
        var view = (window.CM6 && window.CM6.editors) ? window.CM6.editors[targetId] : null;
        try { area.value = JSON.parse(src); } catch (e) { return; }
        if (view && view.dispatch) {
            view.dispatch({ changes: { from: 0, to: view.state.doc.length, insert: area.value } });
            view.focus();
        }
    }
    document.addEventListener('click', function (event) {
        var node = event.target;
        if (!node) return;
        var button = node.closest ? node.closest('[data-sl-robots-template]') : null;
        if (!button) return;
        applyRobotsTemplate(button);
    });
})();
