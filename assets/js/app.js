/* Spoločné správanie pre všetky stránky */
(function ($) {
    'use strict';
    const MD = window.MD = window.MD || {};

    /* Téma */
    $('#themeToggle').on('click', function () {
        const next = document.documentElement.getAttribute('data-bs-theme') === 'dark' ? 'light' : 'dark';
        document.documentElement.setAttribute('data-bs-theme', next);
        MD.store.pref('theme', next);
    });

    /* Toast notifikácie */
    MD.toast = function (msg, type) {
        type = type || 'success';
        const icon = { success: 'bi-check-circle-fill', danger: 'bi-exclamation-triangle-fill', info: 'bi-info-circle-fill' }[type] || 'bi-info-circle-fill';
        const $t = $(
            '<div class="toast align-items-center border-0 app-toast app-toast-' + type + '" role="status" aria-live="polite" aria-atomic="true">' +
            '<div class="d-flex"><div class="toast-body"><i class="bi ' + icon + ' me-2"></i></div>' +
            '<button type="button" class="btn-close me-2 m-auto" data-bs-dismiss="toast" aria-label="Zavrieť"></button></div></div>'
        );
        $t.find('.toast-body').append(document.createTextNode(msg));
        $('#toastArea').append($t);
        const t = new bootstrap.Toast($t[0], { delay: 2200 });
        $t.on('hidden.bs.toast', () => $t.remove());
        t.show();
    };

    /* ------------------------------------------------------------------ */
    /* Späť hore                                                           */
    /* ------------------------------------------------------------------ */
    const $top = $('#backToTop');
    const onScroll = () => $top.toggleClass('show', window.scrollY > 400);
    window.addEventListener('scroll', onScroll, { passive: true });
    onScroll();
    $top.on('click', () => {
        const reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        window.scrollTo({ top: 0, behavior: reduce ? 'auto' : 'smooth' });
    });

    /* ------------------------------------------------------------------ */
    /* PWA – service worker + tlačidlo inštalácie                          */
    /* ------------------------------------------------------------------ */
    const base = (window.MD_BASE || '').replace(/\/$/, '');
    if ('serviceWorker' in navigator && (location.protocol === 'https:' || location.hostname === 'localhost' || location.hostname === '127.0.0.1')) {
        window.addEventListener('load', () => {
            navigator.serviceWorker.register(base + '/sw.js', { scope: base + '/' })
                .catch((e) => console.warn('Service worker sa nepodarilo zaregistrovať', e));
        });
    }

    const isStandalone = () => window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true;
    const isIOS = /iphone|ipad|ipod/i.test(navigator.userAgent) || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
    const $install = $('#installBtn');
    let deferredPrompt = null;

    MD.installState = () => ({ standalone: isStandalone(), canPrompt: !!deferredPrompt, ios: isIOS });

    if (!isStandalone()) {
        // Chrome, Edge, Samsung… – prehliadač ponúkne vlastný dialóg
        window.addEventListener('beforeinstallprompt', (e) => {
            e.preventDefault();
            deferredPrompt = e;
            $install.prop('hidden', false);
        });
        // iPhone / iPad – len návod (Safari nepodporuje beforeinstallprompt)
        if (isIOS) $install.prop('hidden', false);
    }

    $install.on('click', async () => {
        if (deferredPrompt) {
            deferredPrompt.prompt();
            const choice = await deferredPrompt.userChoice;
            deferredPrompt = null;
            if (choice.outcome === 'accepted') $install.prop('hidden', true);
        } else if (isIOS) {
            bootstrap.Modal.getOrCreateInstance('#iosInstallModal').show();
        }
    });

    window.addEventListener('appinstalled', () => {
        deferredPrompt = null;
        $install.prop('hidden', true);
        MD.toast('Aplikácia bola nainštalovaná');
    });
    window.matchMedia('(display-mode: standalone)').addEventListener?.('change', (e) => { if (e.matches) $install.prop('hidden', true); });

    /* ------------------------------------------------------------------ */
    /* Mobilné menu – zatvorí sa po kliknutí na odkaz, mimo menu alebo Esc  */
    /* ------------------------------------------------------------------ */
    const navEl = document.getElementById('mainNav');
    if (navEl) {
        const collapse = () => { if (navEl.classList.contains('show')) bootstrap.Collapse.getOrCreateInstance(navEl, { toggle: false }).hide(); };
        navEl.addEventListener('click', (e) => { if (e.target.closest('a.nav-link:not(.dropdown-toggle), a.dropdown-item')) collapse(); });
        document.addEventListener('click', (e) => { if (!e.target.closest('.app-navbar')) collapse(); });
        document.addEventListener('keydown', (e) => { if (e.key === 'Escape') collapse(); });
    }

    /* Tooltipy */
    document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach((el) => new bootstrap.Tooltip(el));

    /* ------------------------------------------------------------------ */
    /* Obnovenie nastavení prehliadača (pätička)                           */
    /* ------------------------------------------------------------------ */
    $('#resetGo').on('click', async function () {
        const parts = new Set($('#resetParts input:checked').map((_, el) => el.value).get());
        if (!parts.size) { MD.toast('Vyberte, čo chcete vymazať.', 'info'); return; }
        $(this).prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span>Mažem…');
        const ls = (() => { try { return window.localStorage; } catch (e) { return null; } })();
        const keys = [];
        try { for (let i = 0; ls && i < ls.length; i++) keys.push(ls.key(i)); } catch (e) { /* nič */ }
        const drop = (test) => keys.filter((k) => k && k.startsWith('matchday.') && test(k)).forEach((k) => { try { ls.removeItem(k); } catch (e) { /* nič */ } });
        if (parts.has('templates')) drop((k) => k.startsWith('matchday.templates') || k.startsWith('matchday.bg.') || k.startsWith('matchday.fonts') || k.startsWith('matchday.brand'));
        if (parts.has('photos')) drop((k) => k.startsWith('matchday.players'));
        if (parts.has('prefs')) drop((k) => k.startsWith('matchday.prefs'));
        if (parts.has('client')) drop((k) => k === 'matchday.clientId');
        try { Object.keys(sessionStorage).filter((k) => k.startsWith('matchday.')).forEach((k) => sessionStorage.removeItem(k)); } catch (e) { /* nič */ }
        // IndexedDB: pozadia, písma a fotky – celú databázu iba ak sa maže všetko z nej
        if (parts.has('templates') && parts.has('photos') && window.indexedDB) {
            await new Promise((res) => { const r = indexedDB.deleteDatabase('matchday'); r.onsuccess = r.onerror = r.onblocked = () => res(); });
        } else {
            try {
                if (parts.has('templates') && MD.store && MD.store.resetAll) await MD.store.resetAll();
                if (parts.has('templates') && MD.store && MD.store.removeBrandImage) for (const k of ['logo', 'sponsors']) await MD.store.removeBrandImage(k);
                if (parts.has('templates') && MD.store && MD.store.listFonts) for (const f of await MD.store.listFonts()) await MD.store.removeFont(f.name).catch(() => null);
                if (parts.has('photos') && MD.store && MD.store.clearPhotos) await MD.store.clearPhotos();
            } catch (e) { /* nič */ }
        }
        if (parts.has('cache') && window.caches) {
            try { for (const k of await caches.keys()) if (k.startsWith('md-')) await caches.delete(k); } catch (e) { /* nič */ }
        }
        MD.toast('Nastavenia boli obnovené');
        setTimeout(() => location.reload(), 600);
    });
})(jQuery);
