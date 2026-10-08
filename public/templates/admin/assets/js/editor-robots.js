(function () {
    function applyRobotsTemplate(button) {
        var src = button.getAttribute('data-sl-robots-template');
        var targetId = button.getAttribute('data-sl-robots-target') || 'code';
        var area = document.getElementById(targetId);
        var text = '';
        if (!area || !src) return;
        try { text = JSON.parse(src); } catch (e) { return; }
        if (window.SlaedEditor && window.SlaedEditor.setText(targetId, text)) return;
        area.value = text;
    }
    document.addEventListener('click', function (event) {
        var node = event.target;
        if (!node) return;
        var button = node.closest ? node.closest('[data-sl-robots-template]') : null;
        if (!button) return;
        applyRobotsTemplate(button);
    });
})();
