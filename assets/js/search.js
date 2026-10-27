/* Globálne hľadanie (hore v menu): kluby zo Sportnetu, naposledy otvorené kluby,
   družstvá a hráči, ktorých ste si prezreli, a stránky aplikácie. */
(function ($) {
    'use strict';
    const MD = window.MD = window.MD || {};
    const BASE = (window.MD_BASE || '').replace(/\/$/, '');
    const fold = (s) => String(s || '').normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase().trim();
    const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    const pref = (k) => { try { return (MD.store && MD.store.pref(k)) || []; } catch (e) { return []; } };

    const PAGES = [
        { t: 'Zápasy – hľadať klub', u: '/', i: 'bi-search', k: 'domov zapasy hladat klub sezona' },
        { t: 'Galéria obrázkov', u: '/galeria', i: 'bi-images', k: 'galeria obrazky stiahnut' },
        { t: 'Šablóny a vzhľad', u: '/nastavenia', i: 'bi-sliders', k: 'sablony nastavenia editor pozadie pismo vzhlad' },
        { t: 'Návod', u: '/navod', i: 'bi-book', k: 'navod pomoc ako help' },
        { t: 'Správca', u: '/spravca', i: 'bi-shield-lock', k: 'spravca admin heslo log' },
    ];

    /** Uloží družstvo (a jeho hráčov) medzi naposledy prezerané – volá stránka družstva */
    MD.rememberTeam = function (t) {
        try {
            const list = pref('seen').filter((x) => x.teamId !== t.teamId);
            list.unshift(t);
            MD.store.pref('seen', list.slice(0, 8));
        } catch (e) { /* bez úložiska nič */ }
    };

    const clubUrl = (id, season) => BASE + '/match-day-season-detail?' + new URLSearchParams({ s: season || '', team: id });
    const teamUrl = (t) => BASE + '/druzstvo?' + new URLSearchParams({ team: t.teamId, s: t.season || '', cat: t.cat || '' });
    const playerUrl = (t, p) => BASE + '/hrac?' + new URLSearchParams(Object.assign(p.id ? { id: p.id } : {}, { team: t.teamId, p: p.key, s: t.season || '', cat: t.cat || '' }));

    function init(input) {
        const $in = $(input);
        const $form = $in.closest('.gsearch');
        const $menu = $form.find('.gsearch-menu');
        const seasonSel = input.dataset.seasonSelect;
        let items = [], active = -1, timer = null, seq = 0, remote = { q: null, clubs: [], players: [], season: '', loading: false, error: false };

        const season = () => (seasonSel && $(seasonSel).val()) || remote.season || '';

        function localResults(q) {
            const f = fold(q);
            const out = { recent: [], teams: [], players: [], pages: [] };
            pref('recent').forEach((r) => {
                if (!f || fold(r.teamName).includes(f)) out.recent.push({ href: clubUrl(r.teamId, r.season), title: r.teamName, sub: 'Klub · ' + r.season, icon: 'bi-clock-history', logo: r.logo });
            });
            pref('seen').forEach((t) => {
                const label = (t.club || '') + (t.cat ? ' – ' + t.cat : '');
                if (!f || fold(label).includes(f)) out.teams.push({ href: teamUrl(t), title: label, sub: 'Družstvo · ' + (t.season || ''), icon: 'bi-people', logo: t.logo });
                if (f.length >= 2) (t.players || []).forEach((p) => {
                    if (fold(p.name).includes(f)) out.players.push({ href: playerUrl(t, p), title: p.name, sub: (p.nr ? '#' + p.nr + ' · ' : '') + label, icon: 'bi-person', pid: p.id || '' });
                });
            });
            if (f) PAGES.forEach((p) => { if (fold(p.t + ' ' + p.k).includes(f)) out.pages.push({ href: BASE + p.u, title: p.t, sub: 'Stránka', icon: p.i }); });
            out.recent = out.recent.slice(0, f ? 4 : 5);
            out.teams = out.teams.slice(0, f ? 4 : 4);
            out.players = out.players.slice(0, 6);
            return out;
        }

        function render() {
            const q = $in.val().trim();
            const L = localResults(q);
            const groups = [];
            if (!q) {
                if (L.recent.length) groups.push(['Naposledy otvorené kluby', L.recent]);
                if (L.teams.length) groups.push(['Prezerané družstvá', L.teams]);
            } else {
                const clubs = remote.q === q ? remote.clubs.map((c) => ({ href: clubUrl(c.id, season()), title: c.name, sub: c.active === false ? 'Klub · neaktívny' : 'Klub · ' + season(), icon: 'bi-shield', logo: c.logo, club: c })) : [];
                if (clubs.length) groups.push(['Kluby', clubs]);
                // hráči: najprv z prezeraných družstiev (s klubom), potom zo Sportnetu
                const seenIds = new Set(L.players.map((p) => p.pid).filter(Boolean));
                const remoteP = remote.q === q ? (remote.players || []).filter((p) => !seenIds.has(p.id)).map((p) => ({ href: BASE + '/hrac?id=' + encodeURIComponent(p.id), title: p.name, sub: 'Hráč' + (p.birthyear ? ' · nar. ' + p.birthyear : ''), icon: 'bi-person', logo: p.photo })) : [];
                const players = L.players.concat(remoteP).slice(0, 8);
                if (players.length) groups.push(['Hráči', players]);
                if (L.teams.length) groups.push(['Družstvá', L.teams]);
                if (L.recent.length && !clubs.length) groups.push(['Naposledy otvorené', L.recent]);
                if (L.pages.length) groups.push(['Stránky', L.pages]);
            }
            items = [];
            let html = '';
            groups.forEach(([label, list]) => {
                html += '<div class="gsearch-group">' + esc(label) + '</div>';
                list.forEach((it) => {
                    const i = items.push(it) - 1;
                    const small = it.logo && /^https?:/.test(it.logo) ? BASE + '/api-logo?s=64&fb=1' + (it.icon === 'bi-person' ? '&raw=1' : '') + '&url=' + encodeURIComponent(it.logo) : it.logo;
                    const pic = it.logo ? '<img src="' + esc(small) + '" alt="" loading="lazy" onerror="this.replaceWith(Object.assign(document.createElement(\'i\'),{className:\'bi ' + it.icon + '\'}))">' : '<i class="bi ' + it.icon + '"></i>';
                    html += '<a class="gsearch-item" role="option" id="gs-' + i + '" data-i="' + i + '" href="' + esc(it.href) + '"><span class="pic">' + pic + '</span><span class="txt"><span class="t">' + esc(it.title) + '</span><span class="s">' + esc(it.sub) + '</span></span></a>';
                });
            });
            if (q.length >= 2 && remote.loading && remote.q !== q) html += '<div class="gsearch-note"><span class="spinner-border spinner-border-sm me-2"></span>Hľadám kluby…</div>';
            else if (q.length >= 2 && !items.length) html += '<div class="gsearch-note">' + (remote.error ? 'Hľadanie v Sportnete je teraz nedostupné.' : 'Nič sa nenašlo. Skúste časť názvu obce, klubu alebo priezvisko hráča s diakritikou.') + '</div>';
            else if (q.length === 1) html += '<div class="gsearch-note">Napíšte aspoň 2 znaky…</div>';
            else if (!q && !items.length) html += '<div class="gsearch-note">Napíšte názov klubu (napr. obec) alebo meno hráča.</div>';
            $menu.html(html).prop('hidden', false);
            $in.attr('aria-expanded', 'true');
            active = items.length && q ? 0 : -1;
            highlight();
        }

        function highlight() {
            $menu.find('.gsearch-item').removeClass('active');
            if (active >= 0) {
                const el = $menu.find('[data-i="' + active + '"]').addClass('active')[0];
                if (el) { el.scrollIntoView({ block: 'nearest' }); $in.attr('aria-activedescendant', el.id); }
            } else $in.removeAttr('aria-activedescendant');
        }

        function search(q) {
            clearTimeout(timer);
            if (q.length < 2) { render(); return; }
            remote.loading = true;
            render();
            timer = setTimeout(() => {
                const my = ++seq;
                fetch(BASE + '/api-search?q=' + encodeURIComponent(q), { headers: { Accept: 'application/json' } })
                    .then((r) => r.json())
                    .then((d) => { if (my !== seq) return; remote = { q, clubs: d.clubs || [], players: d.players || [], season: d.season || remote.season, loading: false, error: !!d.error }; render(); })
                    .catch(() => { if (my !== seq) return; remote = { q, clubs: [], players: [], season: remote.season, loading: false, error: true }; render(); });
            }, 220);
        }

        function go(it) {
            if (!it) return;
            if (it.club) {
                try {
                    const list = pref('recent').filter((r) => !(r.teamId === it.club.id && r.season === season()));
                    list.unshift({ teamId: it.club.id, teamName: it.club.name, season: season(), logo: it.club.logo || '' });
                    MD.store.pref('recent', list.slice(0, 6));
                } catch (e) { /* nič */ }
            }
            window.location.href = it.href;
        }

        function close() { $menu.prop('hidden', true); $in.attr('aria-expanded', 'false'); }

        $in.on('input', () => search($in.val().trim()));
        $in.on('focus', () => render());
        $in.on('keydown', (e) => {
            if (e.key === 'ArrowDown') { e.preventDefault(); if (items.length) { active = (active + 1) % items.length; highlight(); } }
            else if (e.key === 'ArrowUp') { e.preventDefault(); if (items.length) { active = (active - 1 + items.length) % items.length; highlight(); } }
            else if (e.key === 'Enter') { e.preventDefault(); go(items[active >= 0 ? active : 0]); }
            else if (e.key === 'Escape') { close(); input.blur(); }
        });
        $menu.on('mousedown', '.gsearch-item', (e) => { e.preventDefault(); go(items[+e.currentTarget.dataset.i]); });
        $(document).on('mousedown', (e) => { if (!$form[0].contains(e.target)) close(); });
        $form.on('submit', (e) => { e.preventDefault(); go(items[active >= 0 ? active : 0]); });
    }

    $('[data-gsearch]').each(function () { init(this); });

    // klávesová skratka "/" alebo Ctrl+K – hľadanie v menu
    document.addEventListener('keydown', (e) => {
        const tag = (e.target.tagName || '').toLowerCase();
        const typing = tag === 'input' || tag === 'textarea' || tag === 'select' || e.target.isContentEditable;
        if ((e.key === '/' && !typing) || ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'k')) {
            const el = document.querySelector('[data-gsearch-main]') || document.querySelector('[data-gsearch]');
            if (el) { e.preventDefault(); el.focus(); el.select(); }
        }
    });
})(jQuery);
