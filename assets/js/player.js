/* Profil hráča – nahratie fotky (uloží sa v prehliadači), prepínač sezóny a filter zápasov */
(function ($) {
    'use strict';
    const MD = window.MD;
    const box = document.getElementById('photoBox');
    if (!box) return;
    const key = box.dataset.key;
    const $img = $('#photoImg');
    let url = null;

    async function show() {
        const blob = await MD.store.getPhoto(key);
        if (url) URL.revokeObjectURL(url);
        url = blob ? URL.createObjectURL(blob) : null;
        const src = url || box.dataset.remote || ''; // bez vlastnej fotky fotka zo Sportnetu, ak existuje
        $img.attr('src', src).prop('hidden', !src);
        box.classList.toggle('has-photo', !!src);
        $('#photoRemove').prop('hidden', !url);
    }

    async function setFile(file) {
        if (!file || !/^image\//.test(file.type)) { MD.toast('Vyberte obrázok (PNG, JPG, WEBP).', 'danger'); return; }
        try {
            await MD.store.setPhoto(key, await MD.store.shrinkImage(file, 1200));
            await show();
            MD.toast('Fotka uložená v prehliadači');
        } catch (e) {
            MD.toast(e.message || 'Fotku sa nepodarilo uložiť.', 'danger');
        }
    }

    $('#photoFile').on('change', function () { setFile(this.files[0]); this.value = ''; });
    $('#photoDrop')
        .on('dragover dragenter', (e) => { e.preventDefault(); $('#photoDrop').addClass('drag'); })
        .on('dragleave drop', (e) => { e.preventDefault(); $('#photoDrop').removeClass('drag'); })
        .on('drop', (e) => setFile(e.originalEvent.dataTransfer.files[0]));
    $('#photoRemove').on('click', async () => { await MD.store.removePhoto(key); await show(); MD.toast('Fotka odstránená', 'info'); });

    show();

    /* ---------- prepínač sezóny / družstva ---------- */
    (function () {
        const el = document.getElementById('psData');
        if (!el) return;
        let D; try { D = JSON.parse(el.textContent); } catch (e) { return; }
        const $s = $('#psSeason'), $t = $('#psTeam');
        const go = (t) => {
            const q = new URLSearchParams({ team: t.id, s: t.s, cat: t.cat });
            if (D.sid) q.set('id', D.sid); else q.set('p', D.key);
            location.href = D.base + '?' + q;
        };
        const fill = () => {
            const list = D.teams.filter((t) => t.s === $s.val());
            $t.empty().append(list.map((t) => $('<option>').val(t.id).text([t.name, t.cat].filter(Boolean).join(' · ') + (t.n ? ' (' + t.n + ')' : '')).prop('selected', t.id === D.current)));
            $t.prop('disabled', list.length < 2 && list[0] && list[0].id === D.current);
            return list;
        };
        fill();
        $s.on('change', () => { const l = fill(); if (l[0] && !l.some((t) => t.id === D.current)) go(l[0]); });
        $t.on('change', () => { const t = D.teams.find((x) => x.id === $t.val()); if (t && t.id !== D.current) go(t); });
    })();

    /* ---------- filter zápasov v sezóne ---------- */
    (function () {
        const $f = $('#pFilter');
        if (!$f.length) return;
        const rows = $f.closest('.card-app').find('tbody tr').get();
        const st = { place: '', res: '', role: '', part: '', goal: false, card: false };
        const pl = (n, a, b, c) => n + ' ' + (n === 1 ? a : n >= 2 && n <= 4 ? b : c);
        function apply() {
            let n = 0, min = 0, minKnown = false, g = 0, y = 0, r = 0;
            const res = { W: 0, D: 0, L: 0 };
            rows.forEach((tr) => {
                const d = tr.dataset;
                const ok = (!st.place || d.place === st.place) && (!st.res || d.res === st.res) && (!st.role || d.role === st.role) && (!st.part || d.part === st.part)
                    && (!st.goal || +d.goals > 0) && (!st.card || +d.y + +d.r > 0);
                tr.hidden = !ok;
                if (!ok) return;
                n++; g += +d.goals; y += +d.y; r += +d.r;
                if (d.min !== '') { min += +d.min; minKnown = true; }
                if (res[d.res] !== undefined) res[d.res]++;
            });
            const active = !!(st.place || st.res || st.role || st.part || st.goal || st.card);
            const N = rows.length;
            const parts = [active ? '<strong>' + n + '</strong> z ' + N + (N === 1 ? ' zápasu' : ' zápasov') : '<strong>' + N + '</strong> ' + pl(N, 'zápas', 'zápasy', 'zápasov').split(' ')[1]];
            if (minKnown) parts.push('<strong>' + min.toLocaleString('sk-SK') + '</strong> min');
            parts.push('<strong>' + g + '</strong> ' + pl(g, 'gól', 'góly', 'gólov').split(' ')[1]);
            if (res.W + res.D + res.L) parts.push('<span class="pf-wdl"><b class="w">' + res.W + 'V</b> <b class="d">' + res.D + 'R</b> <b class="l">' + res.L + 'P</b></span>');
            if (y + r) parts.push('<span class="card-ic yellow"></span>' + y + (r ? ' <span class="card-ic red"></span>' + r : ''));
            if (minKnown && g && n) parts.push('gól každých <strong>' + Math.round(min / g) + '</strong> min');
            $('#pfSum').html(parts.join('<span class="sep">·</span>'));
            $('#pfEmpty').prop('hidden', n > 0);
            $f.closest('.card-app').find('.table-responsive').prop('hidden', n === 0);
            $('#pfReset').prop('hidden', !active);
        }
        $f.on('click', '[data-f]', function () {
            st[this.dataset.f] = this.dataset.v;
            $(this).addClass('active').siblings('[data-f]').removeClass('active');
            apply();
        });
        $f.on('click', '[data-t]', function () {
            const k = this.dataset.t; st[k] = !st[k];
            $(this).toggleClass('active', st[k]).attr('aria-pressed', st[k]);
            apply();
        });
        $('#pfReset').on('click', () => {
            Object.assign(st, { place: '', res: '', role: '', part: '', goal: false, card: false });
            $f.find('[data-f]').removeClass('active').filter('[data-v=""]').addClass('active');
            $f.find('[data-t]').removeClass('active').attr('aria-pressed', false);
            apply();
        });
        apply();
    })();
})(jQuery);
