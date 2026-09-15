{{-- Shrinks [data-fit] text until it fits its box, then flags the page as ready for Chromium. --}}
<script>
(function () {
    function fit(el) {
        var box = el.parentElement;
        var size = parseFloat(window.getComputedStyle(el).fontSize);
        var min = parseFloat(el.getAttribute('data-fit-min') || '16');
        var guard = 0;

        while (guard++ < 80 && size > min &&
               (el.scrollWidth > box.clientWidth || el.scrollHeight > box.clientHeight)) {
            size -= 1;
            el.style.fontSize = size + 'px';
        }
    }

    function run() {
        var els = document.querySelectorAll('[data-fit]');
        for (var i = 0; i < els.length; i++) fit(els[i]);
        window.__certReady = true;
    }

    if (document.fonts && document.fonts.ready) {
        document.fonts.ready.then(run, run);
    } else {
        run();
    }
})();
</script>
