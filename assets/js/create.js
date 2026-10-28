/* „Vytvoriť obrázok“ – plávajúce tlačidlo a inteligentný panel (skupiny pripravuje includes/create.php) */
(function ($) {
    'use strict';
    const MD = window.MD = window.MD || {};
    const el = document.getElementById('mdCreate');
    if (!el || !window.bootstrap) return;
    const base = (window.MD_BASE || '').replace(/\/$/, '');
    const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    const logo = (u) => (u ? base + '/api-logo?s=128&fb=1&url=' + encodeURIComponent(u) : ''); // malé WebP; pri chybe originál
    const oc = bootstrap.Offcanvas.getOrCreateInstance(el);
    let groups = [];
    let pane = document.querySelector('[data-team-pane]:not([hidden])')?.dataset.teamPane || null; // stránka klubu: aktuálne družstvo
    try { groups = JSON.parse(document.getElementById('mdCreateData').textContent || '[]') || []; } catch (e) { groups = []; }

    /* čo používateľ tvorí najčastejšie – také typy idú v skupine vyššie */
    const usage = () => { try { return (MD.store && MD.store.pref('createUse')) || {}; } catch (e) { return {}; } };
    const remember = (type) => { try { const u = usage(); u[type] = (u[type] || 0) + 1; MD.store.pref('createUse', u); } catch (e) { /* nič */ } };
    const sorted = (items) => {
        const u = usage();
        return items.map((it, i) => ({ it, i })).sort((a, b) => (b.it.rec - a.it.rec) || ((u[b.it.type] || 0) - (u[a.it.type] || 0)) || (a.i - b.i)).map((x) => x.it);
    };

    const tile = (it, g, big) => '<a class="mdc-item' + (it.rec ? ' is-rec' : '') + (big ? ' is-big' : '') + '" href="' + esc(it.url) + '" data-type="' + esc(it.type) + '">' +
        '<span class="mdc-ic"><i class="bi ' + esc(it.icon) + '"></i></span>' +
        '<span class="mdc-txt"><span class="mdc-label">' + esc(it.label) + '</span>' +
        '<small>' + esc(big && g ? g.title + (g.cat ? ' · ' + g.cat : '') : it.hint) + '</small>' +
        (it.rec && it.why ? '<span class="mdc-why"><i class="bi bi-stars me-1"></i>' + esc(it.why) + '</span>' : '') + '</span>' +
        '<i class="bi bi-chevron-right mdc-go"></i></a>';

    const head = (g, focus) => '<div class="mdc-head' + (focus ? ' is-focus' : '') + '">' +
        (g.logos && g.logos.length ? '<span class="mdc-logos">' + g.logos.map((u) => '<img src="' + esc(logo(u)) + '" alt="" loading="lazy">').join('') + '</span>' : '<span class="mdc-logos"><i class="bi ' + ({ player: 'bi-person', club: 'bi-shield', team: 'bi-people' }[g.kind] || 'bi-image') + '"></i></span>') +
        '<span class="min-w-0 flex-fill"><span class="mdc-title">' + esc(g.title) + '</span><small class="d-block text-muted-2 text-truncate">' + esc(g.sub || '') + '</small></span>' +
        (g.link ? '<a class="btn btn-sm btn-link text-decoration-none px-1" href="' + esc(g.link) + '" title="Otvoriť detail"><i class="bi bi-box-arrow-up-right"></i></a>' : '') + '</div>';

    const section = (g, focus) => '<section class="mdc-group">' + head(g, focus) + '<div class="mdc-grid">' + sorted(g.items).map((it) => tile(it, g)).join('') + '</div></section>';

    function render(focus) {
        const visible = groups.filter((g) => !g.pane || !pane || g.pane === pane);
        const list = focus ? [focus, ...visible.filter((g) => g.key !== focus.key)] : visible;
        let html = '';
        if (!focus) {
            const rec = [];
            groups.forEach((g) => g.items.forEach((it) => { if (it.rec) rec.push([it, g]); }));
            rec.sort((a, b) => ((b[1].pane === pane) - (a[1].pane === pane)));
            if (rec.length) html += '<div class="mdc-rec"><h6 class="mdc-sec"><i class="bi bi-stars me-1"></i>Odporúčané teraz</h6>' + rec.slice(0, 4).map(([it, g]) => tile(it, g, true)).join('') + '</div>';
        }
        html += list.map((g, i) => (i === 1 && focus ? '<h6 class="mdc-sec mt-4">Ďalšie na tejto stránke</h6>' : '') + section(g, focus && i === 0)).join('');
        if (!list.length) html = '<div class="empty-state"><i class="bi bi-search"></i>Nájdite klub, družstvo, zápas alebo hráča – tu sa ukáže, aké obrázky môžete vytvoriť.</div>';
        $('#mdCreateBody').html(html);
    }

    function updateFab() {
        const rec = groups.reduce((n, g) => n + g.items.filter((it) => it.rec).length, 0);
        $('#mdFab').prop('hidden', !groups.length);
        $('#mdFabBadge').text(rec).prop('hidden', !rec);
        document.body.classList.toggle('has-fab', !!groups.length);
    }

    MD.create = {
        /** pridá skupiny (napr. z api-create na úvodnej stránke) */
        add(list) { (list || []).forEach((g) => { if (g && g.items && g.items.length && !groups.some((x) => x.key === g.key)) groups.push(g); }); updateFab(); },
        open(focus) { render(focus || null); oc.show(); },
        /** stránka klubu: prepnuté družstvo */
        setPane(id) { pane = id || null; },
    };

    $('#mdFab').on('click', () => MD.create.open());
    $(document).on('click', '[data-md-create]', function (e) {
        e.preventDefault();
        let g = null;
        try { g = JSON.parse(this.getAttribute('data-md-create')); } catch (err) { /* nič */ }
        MD.create.open(g);
    });
    $('#mdCreateBody').on('click', '.mdc-item', function () { remember(this.dataset.type); });
    updateFab();
})(jQuery);
