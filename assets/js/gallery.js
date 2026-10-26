/* Galéria uložených obrázkov – filter, hľadanie, zoradenie, stránkovanie a náhľad (lightbox) */
(function ($) {
    'use strict';
    const MD = window.MD;
    const CFG = window.MD_GALLERY || {};
    const TYPES = CFG.types || {};
    const PAGE = 24;
    const MONTHS = ['Jan', 'Feb', 'Mar', 'Apr', 'Máj', 'Jún', 'Júl', 'Aug', 'Sep', 'Okt', 'Nov', 'Dec'];

    let items = [];       // všetko zo servera
    let view = [];        // po filtri a zoradení
    let shown = PAGE;     // koľko kariet je vykreslených
    let type = '';
    let q = '';
    let sort = MD.store.pref('gallerySort') || 'updated-desc';
    let showHidden = true;
    let current = -1;     // index v "view" otvorený v lightboxe

    /* ------------------------------------------------------------------ */
    /* Pomocné funkcie                                                     */
    /* ------------------------------------------------------------------ */
    const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    const bytes = (b) => b >= 1048576 ? (b / 1048576).toFixed(1) + ' MB' : Math.max(1, Math.round((b || 0) / 1024)) + ' kB';
    const fold = (s) => String(s || '').toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '');
    const absUrl = (it) => (CFG.root || location.origin) + it.url.split('?')[0];
    const title = (it) => it.d1name + ' vs ' + it.d2name;
    const typeName = (it) => it.typeName || TYPES[it.type] || it.type;
    const dlName = (it) => (it.type || 'obrazok') + '_' + it.file.split('/').pop();

    function fmtDate(iso, withTime) {
        const d = new Date(iso);
        if (!iso || isNaN(d)) return '';
        const p = {};
        new Intl.DateTimeFormat('en-GB', { timeZone: 'Europe/Bratislava', year: 'numeric', month: 'numeric', day: 'numeric', hour: '2-digit', minute: '2-digit', hour12: false })
            .formatToParts(d).forEach((x) => { p[x.type] = x.value; });
        return withTime ? (+p.day) + '.' + (+p.month) + '.' + p.year + ' ' + p.hour + ':' + p.minute : (+p.day) + '. ' + MONTHS[p.month - 1] + ' ' + p.year;
    }

    function api(body) {
        const headers = MD.clientHeaders({ 'Content-Type': 'application/json' });
        if (CFG.csrf) headers['X-CSRF-Token'] = CFG.csrf;
        return fetch('api-gallery', { method: 'POST', headers, body: JSON.stringify(body) })
            .then((r) => r.json().catch(() => ({ error: 'HTTP ' + r.status })))
            .then((j) => { if (!j.ok) throw new Error(j.error || 'chyba'); return j; });
    }

    // admin v režime "všetky" maže natrvalo, inak sa obrázok iba skryje z vlastnej galérie
    const canDelete = () => !!CFG.all;
    const canHide = (it) => !CFG.all && it.owner !== false;

    /* ------------------------------------------------------------------ */
    /* Vykreslenie mriežky                                                 */
    /* ------------------------------------------------------------------ */
    function card(it, i) {
        const score = it.score1 !== '' && it.score1 != null ? '<strong>' + esc(it.score1) + ':' + esc(it.score2) + '</strong> · ' : '';
        const removeBtn = canDelete()
            ? '<button type="button" class="btn btn-outline-danger" data-remove title="Zmazať zo servera" aria-label="Zmazať zo servera"><i class="bi bi-trash"></i></button>'
            : (canHide(it) ? '<button type="button" class="btn btn-outline-danger" data-remove title="Odstrániť z galérie" aria-label="Odstrániť z galérie"><i class="bi bi-eye-slash"></i></button>' : '');
        return '<div class="col g-item" data-i="' + i + '">' +
            '<div class="card-app gallery-card' + (it.hidden ? ' is-hidden' : '') + '">' +
            '<button type="button" class="gallery-thumb" data-open style="aspect-ratio:' + (+it.width || 1) + '/' + (+it.height || 1) + '" aria-label="Zobraziť ' + esc(typeName(it) + ': ' + title(it)) + '">' +
            '<img src="' + esc(it.thumb || it.url) + '" alt="" loading="lazy" decoding="async">' +
            '<span class="badge rounded-pill text-bg-dark">' + esc(typeName(it)) + '</span>' +
            (it.hidden ? '<span class="badge rounded-pill text-bg-warning g-hidden-badge"><i class="bi bi-eye-slash me-1"></i>skryté</span>' : '') +
            '<span class="gallery-zoom"><i class="bi bi-arrows-fullscreen"></i></span></button>' +
            '<div class="card-body">' +
            '<div class="g-title">' + esc(it.d1name) + ' <span class="text-muted-2">vs</span> ' + esc(it.d2name) + '</div>' +
            '<div class="g-meta">' + score + esc(fmtDate(it.startDate)) + '</div>' +
            '<div class="g-meta text-truncate">' + esc(it.competition) + '</div>' +
            '<div class="g-meta"><i class="bi bi-clock me-1"></i>' + esc(fmtDate(it.updated, true)) + ' · ' + bytes(it.bytes) + '</div>' +
            '<div class="g-actions">' +
            '<a class="btn btn-outline-primary" href="' + esc(it.url) + '" download="' + esc(dlName(it)) + '" title="Stiahnuť" aria-label="Stiahnuť"><i class="bi bi-download"></i></a>' +
            '<button type="button" class="btn btn-outline-secondary" data-copy title="Kopírovať odkaz" aria-label="Kopírovať odkaz"><i class="bi bi-link-45deg"></i></button>' +
            removeBtn +
            '</div></div></div></div>';
    }

    function compute() {
        const qq = fold(q);
        view = items.filter((it) => {
            if (type && it.type !== type) return false;
            if (!showHidden && it.hidden) return false;
            if (qq && !fold([it.d1name, it.d2name, it.competition, it.round, typeName(it), fmtDate(it.startDate)].join(' ')).includes(qq)) return false;
            return true;
        });
        const by = {
            'updated-desc': (a, b) => String(b.updated).localeCompare(String(a.updated)),
            'updated-asc':  (a, b) => String(a.updated).localeCompare(String(b.updated)),
            'match-desc':   (a, b) => String(b.startDate).localeCompare(String(a.startDate)),
            'match-asc':    (a, b) => String(a.startDate).localeCompare(String(b.startDate)),
            'team-asc':     (a, b) => String(a.d1name).localeCompare(String(b.d1name), 'sk') || String(a.d2name).localeCompare(String(b.d2name), 'sk'),
        }[sort] || (() => 0);
        view.sort(by);
    }

    function render() {
        compute();
        $('#gGrid').html(view.slice(0, shown).map(card).join(''));
        $('#gMoreWrap').prop('hidden', view.length <= shown);
        $('#gMore').html('<i class="bi bi-arrow-down-circle me-1"></i>Zobraziť ďalšie (' + Math.min(PAGE, view.length - shown) + ' z ' + (view.length - shown) + ')');

        const base = items.filter((i) => showHidden || !i.hidden);
        $('[data-count]').each(function () {
            const t = this.dataset.count;
            this.textContent = t ? base.filter((i) => i.type === t).length : base.length;
        });
        const total = base.reduce((a, i) => a + (i.bytes || 0), 0);
        const filtered = view.length !== base.length ? ' · zobrazených ' + view.length : '';
        $('#gSummary').text(base.length + ' ' + plural(base.length, 'obrázok', 'obrázky', 'obrázkov') + ' · spolu ' + bytes(total) + filtered);
        $('#gEmpty').prop('hidden', items.length > 0);
        $('.filter-empty').prop('hidden', view.length > 0 || !items.length);
    }

    const plural = (n, one, few, many) => (n === 1 ? one : n >= 2 && n <= 4 ? few : many);

    async function load() {
        try {
            const r = await fetch('api-gallery' + (CFG.all ? '?all=1' : ''), { headers: MD.clientHeaders(), cache: 'no-store' });
            const j = await r.json();
            if (j.error) throw new Error(j.error);
            items = j.items || [];
            render();
        } catch (e) {
            $('#gSummary').text('Galériu sa nepodarilo načítať.');
            MD.toast('Galériu sa nepodarilo načítať.', 'danger');
        }
        $('#gLoading').prop('hidden', true);
    }

    /* ------------------------------------------------------------------ */
    /* Filter, hľadanie, zoradenie, stránkovanie                           */
    /* ------------------------------------------------------------------ */
    $('#gSort').val(sort).on('change', function () {
        sort = this.value;
        MD.store.pref('gallerySort', sort);
        shown = PAGE;
        render();
    });
    if (!$('#gSort').val()) { sort = 'updated-desc'; $('#gSort').val(sort); }

    $('#gFilter').on('click', '[data-type]', function () {
        type = this.dataset.type;
        $(this).addClass('active').attr('aria-pressed', 'true').siblings().removeClass('active').attr('aria-pressed', 'false');
        shown = PAGE;
        render();
    });

    let searchTimer = null;
    $('#gSearch').on('input', function () {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(() => { q = this.value.trim(); shown = PAGE; render(); }, 120);
    });

    $('#gShowHidden').on('change', function () { showHidden = this.checked; shown = PAGE; render(); });

    $('#gReset').on('click', () => {
        q = ''; type = '';
        $('#gSearch').val('');
        $('#gFilter [data-type=""]').trigger('click');
    });

    $('#gMore').on('click', () => { shown += PAGE; render(); });

    /* ------------------------------------------------------------------ */
    /* Akcie na kartách                                                    */
    /* ------------------------------------------------------------------ */
    const itemOf = (el) => view[+$(el).closest('.g-item').data('i')];

    function copyLink(it) {
        navigator.clipboard.writeText(absUrl(it)).then(() => MD.toast('Odkaz skopírovaný'), () => MD.toast('Odkaz sa nepodarilo skopírovať', 'danger'));
    }

    $('#gGrid')
        .on('click', '[data-open]', function () { openAt(+$(this).closest('.g-item').data('i')); })
        .on('click', '[data-copy]', function () { copyLink(itemOf(this)); })
        .on('click', '[data-remove]', function () { askRemove(itemOf(this)); });

    /* ------------------------------------------------------------------ */
    /* Odstránenie (skrytie / trvalé zmazanie)                             */
    /* ------------------------------------------------------------------ */
    const delModal = new bootstrap.Modal('#delModal');
    let toRemove = null;
    let reopenLightbox = false; // potvrdenie spustené z lightboxu (dve okná naraz Bootstrap nepodporuje)

    function askRemove(it) {
        if (!it) return;
        toRemove = it;
        const del = canDelete();
        $('#delIcon').attr('class', 'bi text-danger fs-1 d-block mb-2 ' + (del ? 'bi-trash' : 'bi-eye-slash'));
        $('#delText').text(del ? 'Natrvalo zmazať obrázok zo servera?' : 'Odstrániť obrázok z vašej galérie?');
        $('#delNote').text(del ? title(it) + ' · ' + typeName(it) + '. Túto akciu nie je možné vrátiť.' : 'Súbor zostane uložený na serveri.');
        $('#delOk').text(del ? 'Zmazať' : 'Odstrániť');
        delModal.show();
    }

    $('#delOk').on('click', async function () {
        const it = toRemove;
        toRemove = null; // potvrdené – zatvorenie okna už lightbox neobnovuje
        delModal.hide();
        if (!it) return;
        try {
            await api({ action: canDelete() ? 'delete' : 'hide', file: it.file });
            items = items.filter((i) => i.file !== it.file);
            render();
            if (reopenLightbox && view.length) openAt(Math.min(current, view.length - 1));
            MD.toast(canDelete() ? 'Obrázok bol zmazaný zo servera' : 'Obrázok odstránený z galérie');
        } catch (e) {
            MD.toast('Nepodarilo sa odstrániť: ' + e.message, 'danger');
            if (reopenLightbox) openAt(current);
        }
        reopenLightbox = false;
    });
    // zrušené potvrdenie z lightboxu -> lightbox sa vráti
    document.getElementById('delModal').addEventListener('hidden.bs.modal', () => {
        if (reopenLightbox && toRemove) { reopenLightbox = false; toRemove = null; openAt(current); }
    });

    /* ------------------------------------------------------------------ */
    /* Lightbox                                                            */
    /* ------------------------------------------------------------------ */
    const lbEl = document.getElementById('gLightbox');
    const lightbox = new bootstrap.Modal(lbEl);
    const $img = $('#lbImg');
    const canShareFiles = !!(navigator.canShare && window.File && navigator.canShare({ files: [new File([''], 'x.png', { type: 'image/png' })] }));

    function openAt(i) {
        show(i);
        lightbox.show();
    }

    function show(i) {
        if (!view.length) return;
        current = (i + view.length) % view.length;
        const it = view[current];
        $('#lbSpin').show();
        $img.removeClass('is-loaded').attr('alt', typeName(it) + ': ' + title(it));
        $img[0].onload = () => { $('#lbSpin').hide(); $img.addClass('is-loaded'); };
        $img[0].onerror = () => { $('#lbSpin').hide(); };
        $img.attr('src', it.url);

        const score = it.score1 !== '' && it.score1 != null ? it.score1 + ':' + it.score2 + ' · ' : '';
        $('#lbTitle').text(title(it));
        $('#lbMeta').text(typeName(it) + ' · ' + score + [fmtDate(it.startDate), it.competition].filter(Boolean).join(' · '));
        $('#lbInfo').text((it.width || '?') + '×' + (it.height || '?') + ' px · ' + bytes(it.bytes) + ' · uložené ' + fmtDate(it.updated, true) + (it.hidden ? ' · skryté používateľom' : ''));
        $('#lbDownload').attr({ href: it.url, download: dlName(it) });
        $('#lbOpen').attr('href', it.url);
        $('#lbShare').prop('hidden', !canShareFiles);
        const removable = canDelete() || canHide(it);
        $('#lbRemove').prop('hidden', !removable)
            .attr('title', canDelete() ? 'Zmazať zo servera' : 'Odstrániť z galérie')
            .html('<i class="bi ' + (canDelete() ? 'bi-trash' : 'bi-eye-slash') + '"></i>');
        $('#lbPos').text((current + 1) + ' / ' + view.length);
        $('#lbPrev, #lbNext').prop('hidden', view.length < 2);

        // prednačítanie susedov
        [current + 1, current - 1].forEach((n) => { const nb = view[(n + view.length) % view.length]; if (nb) new Image().src = nb.url; });
    }

    $('#lbPrev').on('click', () => show(current - 1));
    $('#lbNext').on('click', () => show(current + 1));
    $('#lbCopy').on('click', () => copyLink(view[current]));
    $('#lbRemove').on('click', () => {
        const it = view[current];
        reopenLightbox = true;
        lbEl.addEventListener('hidden.bs.modal', () => askRemove(it), { once: true });
        lightbox.hide();
    });
    $('#lbShare').on('click', async () => {
        const it = view[current];
        try {
            const blob = await fetch(it.url).then((r) => r.blob());
            await navigator.share({ files: [new File([blob], dlName(it), { type: 'image/png' })], title: title(it) });
        } catch (e) {
            if (e && e.name !== 'AbortError') MD.toast('Zdieľanie sa nepodarilo', 'danger');
        }
    });

    lbEl.addEventListener('keydown', (e) => {
        if (e.key === 'ArrowLeft') { e.preventDefault(); show(current - 1); }
        if (e.key === 'ArrowRight') { e.preventDefault(); show(current + 1); }
    });

    // potiahnutie prstom na mobile
    let touchX = null;
    const stage = lbEl.querySelector('.lightbox-stage');
    stage.addEventListener('touchstart', (e) => { touchX = e.touches.length === 1 ? e.touches[0].clientX : null; }, { passive: true });
    stage.addEventListener('touchend', (e) => {
        if (touchX === null) return;
        const dx = e.changedTouches[0].clientX - touchX;
        if (Math.abs(dx) > 50) show(current + (dx < 0 ? 1 : -1));
        touchX = null;
    }, { passive: true });

    lbEl.addEventListener('hidden.bs.modal', () => {
        $img.attr('src', '');
        // vráti fokus na kartu, ktorá bola naposledy otvorená
        const card = document.querySelector('.g-item[data-i="' + current + '"] [data-open]');
        if (card) card.focus({ preventScroll: true });
    });

    load();
})(jQuery);
