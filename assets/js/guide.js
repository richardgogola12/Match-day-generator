/* Návod – zväčšenie obrázkov a zvýraznenie aktuálnej kapitoly v obsahu */
(function () {
    'use strict';

    /* Zväčšenie obrázka */
    const zoomEl = document.getElementById('guideZoom');
    if (zoomEl) {
        const modal = new bootstrap.Modal(zoomEl);
        const img = document.getElementById('guideZoomImg');
        const cap = document.getElementById('guideZoomCaption');
        document.addEventListener('click', (e) => {
            const btn = e.target.closest('[data-zoom]');
            if (!btn) return;
            img.src = btn.dataset.zoom;
            img.alt = btn.querySelector('img') ? btn.querySelector('img').alt : '';
            cap.textContent = btn.dataset.caption || '';
            modal.show();
        });
        zoomEl.addEventListener('hidden.bs.modal', () => { img.removeAttribute('src'); });
    }

    /* Obsah – zvýraznenie kapitoly, ktorá je práve na obrazovke */
    const links = Array.from(document.querySelectorAll('.guide-toc a'));
    const byId = new Map(links.map((a) => [a.getAttribute('href').slice(1), a]));
    if ('IntersectionObserver' in window && links.length) {
        const visible = new Set();
        const io = new IntersectionObserver((entries) => {
            entries.forEach((en) => (en.isIntersecting ? visible.add(en.target.id) : visible.delete(en.target.id)));
            const first = Array.from(byId.keys()).find((id) => visible.has(id));
            if (!first) return;
            links.forEach((a) => { a.classList.remove('active'); a.removeAttribute('aria-current'); });
            byId.get(first).classList.add('active');
            byId.get(first).setAttribute('aria-current', 'true');
        }, { rootMargin: '-90px 0px -60% 0px' });
        byId.forEach((_, id) => { const s = document.getElementById(id); if (s) io.observe(s); });
    }

    /* Na mobile sa obsah po kliknutí zbalí */
    const toc = document.getElementById('guideToc');
    if (toc) {
        toc.addEventListener('click', (e) => {
            if (e.target.closest('a') && window.matchMedia('(max-width: 991.98px)').matches && toc.classList.contains('show')) {
                bootstrap.Collapse.getOrCreateInstance(toc, { toggle: false }).hide();
            }
        });
    }
})();
