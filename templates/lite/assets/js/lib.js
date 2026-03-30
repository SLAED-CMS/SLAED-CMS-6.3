(function () {
    function createElement(tag, className, text) {
        var node = document.createElement(tag);
        if (className) node.className = className;
        if (typeof text === 'string') node.textContent = text;
        return node;
    }

    function wrapSlider(list) {
        if (!list || !list.parentNode) return null;
        if (list.parentNode.classList && list.parentNode.classList.contains('bx-viewport')) {
            return list.parentNode.parentNode;
        }

        var wrapper = createElement('div', 'bx-wrapper');
        var viewport = createElement('div', 'bx-viewport');
        list.parentNode.insertBefore(wrapper, list);
        wrapper.appendChild(viewport);
        viewport.appendChild(list);
        return wrapper;
    }

    function initHeroSlider() {
        var list = document.getElementById('slider-head');
        if (!list) return;

        var slides = Array.prototype.slice.call(list.children);
        if (!slides.length) return;

        var wrapper = wrapSlider(list);
        var viewport = wrapper ? wrapper.querySelector('.bx-viewport') : null;
        if (!wrapper || !viewport) return;

        viewport.style.height = '440px';
        viewport.style.position = 'relative';
        list.style.listStyle = 'none';
        list.style.margin = '0';
        list.style.padding = '0';
        list.style.position = 'relative';
        list.style.height = '440px';

        var activeIndex = 0;

        function showSlide(index) {
            activeIndex = index;
            for (var i = 0; i < slides.length; i++) {
                var slide = slides[i];
                slide.style.position = 'absolute';
                slide.style.inset = '0';
                slide.style.opacity = i === index ? '1' : '0';
                slide.style.visibility = i === index ? 'visible' : 'hidden';
                slide.style.transition = 'opacity 0.45s ease, visibility 0.45s ease';
                slide.style.pointerEvents = i === index ? 'auto' : 'none';
            }

            if (pager) {
                var links = pager.querySelectorAll('a');
                for (var j = 0; j < links.length; j++) {
                    links[j].classList.toggle('active', j === index);
                }
            }
        }

        function goNext(event) {
            if (event) event.preventDefault();
            showSlide((activeIndex + 1) % slides.length);
        }

        function goPrev(event) {
            if (event) event.preventDefault();
            showSlide((activeIndex - 1 + slides.length) % slides.length);
        }

        var prev = createElement('a', 'bx-prev', 'Prev');
        prev.href = '#';
        prev.addEventListener('click', goPrev);

        var next = createElement('a', 'bx-next', 'Next');
        next.href = '#';
        next.addEventListener('click', goNext);

        wrapper.appendChild(prev);
        wrapper.appendChild(next);

        var pager = createElement('div', 'bx-pager');
        for (var i = 0; i < slides.length; i++) {
            var item = createElement('div', 'bx-pager-item');
            var link = createElement('a', '', String(i + 1));
            link.href = '#';
            (function (index) {
                link.addEventListener('click', function (event) {
                    event.preventDefault();
                    showSlide(index);
                });
            })(i);
            item.appendChild(link);
            pager.appendChild(item);
        }
        wrapper.appendChild(pager);

        showSlide(0);
    }

    function initSitesCarousel() {
        var list = document.getElementById('slaed_sites');
        if (!list) return;

        var slides = Array.prototype.slice.call(list.children);
        if (!slides.length) return;

        var wrapper = wrapSlider(list);
        var viewport = wrapper ? wrapper.querySelector('.bx-viewport') : null;
        if (!wrapper || !viewport) return;

        wrapper.style.position = 'relative';
        viewport.style.overflow = 'hidden';
        viewport.style.width = '100%';

        list.style.display = 'flex';
        list.style.gap = '20px';
        list.style.margin = '0';
        list.style.padding = '0';
        list.style.listStyle = 'none';
        list.style.transition = 'transform 0.4s ease';
        list.style.willChange = 'transform';

        for (var i = 0; i < slides.length; i++) {
            slides[i].style.flex = '0 0 260px';
        }

        var activeIndex = 0;
        var slideWidth = 280;

        function visibleCount() {
            return Math.max(1, Math.floor(viewport.clientWidth / slideWidth));
        }

        function maxIndex() {
            return Math.max(0, slides.length - visibleCount());
        }

        function render() {
            if (activeIndex > maxIndex()) activeIndex = maxIndex();
            list.style.transform = 'translateX(' + (-activeIndex * slideWidth) + 'px)';
            if (prev) prev.classList.toggle('disabled', activeIndex <= 0);
            if (next) next.classList.toggle('disabled', activeIndex >= maxIndex());
        }

        function goPrev(event) {
            if (event) event.preventDefault();
            activeIndex = Math.max(0, activeIndex - visibleCount());
            render();
        }

        function goNext(event) {
            if (event) event.preventDefault();
            activeIndex = Math.min(maxIndex(), activeIndex + visibleCount());
            render();
        }

        var prev = document.getElementById('carousel-left') || createElement('span');
        var next = document.getElementById('carousel-right') || createElement('span');

        prev.className = 'bx-prev';
        next.className = 'bx-next';
        prev.textContent = 'Prev';
        next.textContent = 'Next';

        if (!prev.parentNode) wrapper.appendChild(prev);
        if (!next.parentNode) wrapper.appendChild(next);

        prev.addEventListener('click', goPrev);
        next.addEventListener('click', goNext);

        window.addEventListener('resize', render);
        render();
    }

    function initLiteUi() {
        initHeroSlider();
        initSitesCarousel();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initLiteUi);
    } else {
        initLiteUi();
    }
})();
