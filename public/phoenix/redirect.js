(function () {
    'use strict';
    const target = new URL('index.html', document.currentScript.src);
    target.search = location.search;
    target.hash = location.hash;
    location.replace(target.href);
})();
