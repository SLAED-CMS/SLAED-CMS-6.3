(function () {
    'use strict';

    var edge = 12;
    var gap = 8;
    var states = ['sl-float-left', 'sl-float-right', 'sl-float-up', 'sl-float-open'];

    function clearState(node) {
        states.forEach(function (name) {
            node.classList.remove(name);
        });
        node.style.removeProperty('--sl-float-left');
        node.style.removeProperty('--sl-float-top');
        node.style.removeProperty('--sl-float-arrow');
    }

    function getPanel(node) {
        if (node.classList.contains('sl-tip')) return node.querySelector(':scope > div');
        if (node.matches('.sl-menu > ul > li')) return node.querySelector(':scope > ul');
        return null;
    }

    function fit(node) {
        var panel = getPanel(node);
        if (!panel) return;

        clearState(node);
        node.classList.add('sl-float-open');
        node.style.setProperty('--sl-float-left', '-9999px');
        node.style.setProperty('--sl-float-top', '-9999px');

        window.requestAnimationFrame(function () {
            var rect = panel.getBoundingClientRect();
            var host = node.getBoundingClientRect();
            var width = rect.width;
            var height = rect.height;
            var left = host.left + (host.width / 2) - (width / 2);
            var top = host.bottom + gap;
            var center = host.left + (host.width / 2);

            if (left + width > window.innerWidth - edge) {
                left = Math.max(edge, window.innerWidth - width - edge);
                node.classList.add('sl-float-right');
            } else if (left < edge) {
                left = edge;
                node.classList.add('sl-float-left');
            }

            if (top + height > window.innerHeight - edge && host.top > height + edge) {
                top = host.top - height - gap;
                node.classList.add('sl-float-up');
            }

            node.style.setProperty('--sl-float-left', left + 'px');
            node.style.setProperty('--sl-float-top', top + 'px');
            node.style.setProperty('--sl-float-arrow', Math.max(14, Math.min(width - 14, center - left)) + 'px');
        });
    }

    function refitOpen() {
        var list = document.querySelectorAll('.sl-float-open');
        Array.prototype.forEach.call(list, fit);
    }

    function bind(root) {
        var list = root.querySelectorAll ? root.querySelectorAll('.sl-tip, .sl-menu > ul > li') : [];
        Array.prototype.forEach.call(list, function (node) {
            if (node.getAttribute('data-sl-float-ready') === '1') return;
            node.setAttribute('data-sl-float-ready', '1');
            node.addEventListener('mouseenter', function () { fit(node); });
            node.addEventListener('focusin', function () { fit(node); });
            node.addEventListener('mouseleave', function () { clearState(node); });
            node.addEventListener('focusout', function () { clearState(node); });
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        bind(document);
    });

    document.addEventListener('htmx:load', function (event) {
        bind(event.target || document);
    });

    window.addEventListener('resize', refitOpen);
    window.addEventListener('scroll', refitOpen, true);
}());
