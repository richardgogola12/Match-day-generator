/* Značka klubu – logo, pás sponzorov a farby; ukladá sa v prehliadači (MD.store.brand) */
(function ($) {
    'use strict';
    const MD = window.MD;
    const store = MD.store;
    const canvas = document.getElementById('bpOut');
    const sq = document.createElement('canvas');
    let cfg = store.brand();
    let brandImgs = { logo: null, sponsors: null };
    let format = 'square';

    // [názov, hlavná, doplnková (štítky), tmavá (pozadie)] – doplnková musí byť svetlá, tmavá dosť tmavá (čitateľnosť)
    const PRESETS = [
        ['Predvolené', '#e63946', '#ffd166', '#0b2a1a'],
        ['Červeno-biele', '#d7192d', '#ffffff', '#1b0508'],
        ['Modro-biele', '#1f6feb', '#8fd3ff', '#0a1a33'],
        ['Nebesky modré', '#3fa9f5', '#ffffff', '#071a2b'],
        ['Kráľovská modrá', '#2541b2', '#ffd700', '#0a1033'],
        ['Trikolóra', '#ee1c25', '#ffffff', '#0b1f4a'],
        ['Zeleno-biele', '#1fa463', '#e9f7ef', '#062014'],
        ['Zeleno-žlté', '#2e9d3a', '#ffe14d', '#07210d'],
        ['Limetkové', '#84cc16', '#f7fee7', '#0d1a05'],
        ['Tyrkysové', '#00b3a4', '#ffe066', '#032321'],
        ['Petrolejové', '#0e7490', '#fde68a', '#031c22'],
        ['Čierno-biele', '#9aa0a6', '#ffffff', '#111111'],
        ['Strieborné', '#8e9aaf', '#e6ebf2', '#0f141c'],
        ['Žlto-čierne', '#f5c400', '#ffffff', '#141414'],
        ['Zlato-čierne', '#c9a227', '#f5e6b8', '#0f0d08'],
        ['Grafit a oranžová', '#f97316', '#ffffff', '#18181b'],
        ['Oranžové', '#ff7a00', '#ffd166', '#1a1208'],
        ['Bordovo-modré', '#8b1538', '#4ea8ff', '#0d1530'],
        ['Vínovo-zlaté', '#7a1f3d', '#e8b04b', '#1a0710'],
        ['Modro-červené', '#e11d48', '#93c5fd', '#0b1533'],
        ['Fialové', '#7b3fe4', '#ffd166', '#160b2e'],
        ['Ružové', '#e83e8c', '#ffd1e6', '#230716'],
        ['Neónové', '#ff2e88', '#00e5ff', '#0a0a1a'],
        ['Hnedo-béžové', '#a0522d', '#f1d7b5', '#1a0f08'],
    ];
    // náhľad predvoľby = miniatúra obrázka: tmavé pozadie, nadpis s tieňom hlavnej farby, štítok doplnkovej
    $('#bcPresets').html(PRESETS.map(([n, a, b, c], i) =>
        '<button type="button" class="brand-preset" data-i="' + i + '" title="' + n + '" aria-label="Farby ' + n + '">' +
        '<span class="bp-mini" style="--p:' + a + ';--a:' + b + ';--b:' + c + '"><span class="bp-title">MATCH</span><span class="bp-pill" style="color:' + c + '">DAY</span><span class="bp-bar"></span></span>' +
        '<small>' + n + '</small></button>').join(''));

    function save() { store.brand(cfg); schedule(); }

    function syncForm() {
        $('#bcOn').prop('checked', !!cfg.colors.on);
        $('#bcPrimary').val(cfg.colors.primary); $('#bcAccent').val(cfg.colors.accent); $('#bcBase').val(cfg.colors.base);
        $('#bcPresets .brand-preset').removeClass('active').filter(function () {
            const p = PRESETS[this.dataset.i];
            return p[1] === cfg.colors.primary && p[2] === cfg.colors.accent && p[3] === cfg.colors.base;
        }).addClass('active');
        $('#blOn').prop('checked', !!cfg.logo.on); $('#blPos').val(cfg.logo.pos);
        $('#blSize').val(cfg.logo.size); $('#blSizeTxt').text(cfg.logo.size + ' px');
        $('#blOpacity').val(cfg.logo.opacity); $('#blOpacityTxt').text(Math.round(cfg.logo.opacity * 100) + ' %');
        $('#bsOn').prop('checked', !!cfg.sponsors.on); $('#bsHeight').val(cfg.sponsors.height); $('#bsHeightTxt').text(cfg.sponsors.height + ' px');
        $('#bsStrip').prop('checked', cfg.sponsors.strip !== false);
        const thumb = (sel, img, rm) => {
            $(sel + ' img').attr('src', img ? img.src : '').prop('hidden', !img);
            $(sel + ' > i, ' + sel + ' > span').prop('hidden', !!img);
            $(rm).prop('hidden', !img);
        };
        thumb('#blThumb', brandImgs.logo, '#blRemove');
        thumb('#bsThumb', brandImgs.sponsors, '#bsRemove');
        $('#bcFromLogo').prop('disabled', !brandImgs.logo);
    }

    async function loadImgs() {
        const b = await store.loadBrand();
        brandImgs = { logo: b.logo, sponsors: b.sponsors };
    }

    /* ---------------- náhľad ---------------- */
    let t = null;
    const schedule = () => { clearTimeout(t); t = setTimeout(preview, 60); };
    const sampleLogos = Promise.all([MD.loadImage(MD.SAMPLE.logos.home), MD.loadImage(MD.SAMPLE.logos.away)]);
    async function preview() {
        const type = $('#bpType').val();
        const tpl = store.loadTemplate(type);
        const theme = cfg.colors.on ? cfg.colors : null;
        const [, bgInfo, [home, away]] = await Promise.all([MD.ensureFonts(tpl), store.backgroundUrl(type), sampleLogos]);
        let bg = await MD.loadImage(bgInfo.url);
        if (theme && !bgInfo.custom) bg = MD.recolorImage(bg, theme);
        const data = Object.assign({}, MD.SAMPLE.data, { players: MD.samplePlayers(24) });
        if (type === 'matchday') { data.score1 = ''; data.score2 = ''; }
        MD.render(sq, tpl, data, { background: bg, logos: { home, away, team: home, opponent: away }, teamLogos: {} }, { theme, scale: 2 });
        MD.compose(canvas, sq, { format, scale: 2, brand: { logo: brandImgs.logo, sponsors: brandImgs.sponsors, cfg }, theme: theme || MD.BASE_COLORS, teamLogo: home, title: 'Muži A · ' + data.competition, subtitle: 'So 31.10. · 14:30' });
        $('#bpFrame').toggleClass('is-story', format === 'story');
    }

    /* ---------------- farby z loga ---------------- */
    function hsl(r, g, b) {
        r /= 255; g /= 255; b /= 255;
        const mx = Math.max(r, g, b), mn = Math.min(r, g, b), l = (mx + mn) / 2;
        if (mx === mn) return [0, 0, l];
        const d = mx - mn, s = l > 0.5 ? d / (2 - mx - mn) : d / (mx + mn);
        const h = mx === r ? (g - b) / d + (g < b ? 6 : 0) : mx === g ? (b - r) / d + 2 : (r - g) / d + 4;
        return [h * 60, s, l];
    }
    const toHex = (r, g, b) => '#' + [r, g, b].map((v) => Math.round(v).toString(16).padStart(2, '0')).join('');
    function hslHex(h, s, l) {
        const k = (n) => (n + h / 30) % 12, a = s * Math.min(l, 1 - l);
        const f = (n) => l - a * Math.max(-1, Math.min(k(n) - 3, Math.min(9 - k(n), 1)));
        return toHex(f(0) * 255, f(8) * 255, f(4) * 255);
    }
    function colorsFromLogo(img) {
        const c = document.createElement('canvas'); c.width = c.height = 80;
        const ctx = c.getContext('2d');
        ctx.drawImage(img, 0, 0, 80, 80);
        const d = ctx.getImageData(0, 0, 80, 80).data;
        const bins = Array.from({ length: 24 }, () => ({ n: 0, r: 0, g: 0, b: 0 }));
        let light = 0, dark = 0, total = 0;
        for (let i = 0; i < d.length; i += 4) {
            if (d[i + 3] < 200) continue;
            total++;
            const [h, s, l] = hsl(d[i], d[i + 1], d[i + 2]);
            if (s < 0.3 || l < 0.12 || l > 0.9) { if (l > 0.85) light++; else if (l < 0.2) dark++; continue; }
            const bn = bins[Math.floor(h / 15) % 24];
            bn.n++; bn.r += d[i]; bn.g += d[i + 1]; bn.b += d[i + 2];
        }
        const ranked = bins.map((b, i) => Object.assign(b, { i })).filter((b) => b.n > total * 0.02).sort((a, b) => b.n - a.n);
        if (!ranked.length) return null;
        const avg = (b) => [b.r / b.n, b.g / b.n, b.b / b.n];
        const p = avg(ranked[0]);
        const second = ranked.find((b) => { const dd = Math.abs(b.i - ranked[0].i) % 24; return Math.min(dd, 24 - dd) >= 3; });
        const ph = hsl(...p);
        return {
            primary: toHex(...p),
            accent: second ? toHex(...avg(second)) : (light >= dark ? '#ffffff' : hslHex(ph[0], 0.9, 0.7)),
            base: hslHex(ph[0], 0.55, 0.09),
        };
    }

    /* ---------------- udalosti ---------------- */
    $('#bcOn').on('change', function () { cfg.colors.on = this.checked; save(); });
    $('#bcPrimary, #bcAccent, #bcBase').on('input', function () {
        cfg.colors[{ bcPrimary: 'primary', bcAccent: 'accent', bcBase: 'base' }[this.id]] = this.value;
        cfg.colors.on = true; syncForm(); save();
    });
    $('#bcPresets').on('click', '.brand-preset', function () {
        const p = PRESETS[this.dataset.i];
        Object.assign(cfg.colors, { primary: p[1], accent: p[2], base: p[3], on: this.dataset.i !== '0' });
        syncForm(); save();
    });
    $('#bcFromLogo').on('click', () => {
        const c = brandImgs.logo && colorsFromLogo(brandImgs.logo);
        if (!c) { MD.toast('V logu sa nenašli výrazné farby.', 'info'); return; }
        Object.assign(cfg.colors, c, { on: true });
        syncForm(); save();
        MD.toast('Farby nastavené podľa loga');
    });

    async function upload(kind, file) {
        if (!file) return;
        try {
            await store.setBrandImage(kind, await store.shrinkImage(file, kind === 'logo' ? 600 : 2400));
            cfg[kind].on = true;
            store.brand(cfg);
            await loadImgs(); syncForm(); schedule();
            MD.toast(kind === 'logo' ? 'Logo uložené v prehliadači' : 'Pás sponzorov uložený v prehliadači');
        } catch (e) { MD.toast(e.message || 'Obrázok sa nepodarilo uložiť.', 'danger'); }
    }
    $('#blFile').on('change', function () { const f = this.files[0]; this.value = ''; upload('logo', f); });
    $('#bsFile').on('change', function () { const f = this.files[0]; this.value = ''; upload('sponsors', f); });
    $('#blRemove').on('click', async () => { await store.removeBrandImage('logo'); await loadImgs(); syncForm(); schedule(); });
    $('#bsRemove').on('click', async () => { await store.removeBrandImage('sponsors'); await loadImgs(); syncForm(); schedule(); });

    $('#blOn').on('change', function () { cfg.logo.on = this.checked; save(); });
    $('#blPos').on('change', function () { cfg.logo.pos = this.value; save(); });
    $('#blSize').on('input', function () { cfg.logo.size = +this.value; $('#blSizeTxt').text(this.value + ' px'); save(); });
    $('#blOpacity').on('input', function () { cfg.logo.opacity = +this.value; $('#blOpacityTxt').text(Math.round(this.value * 100) + ' %'); save(); });
    $('#bsOn').on('change', function () { cfg.sponsors.on = this.checked; save(); });
    $('#bsHeight').on('input', function () { cfg.sponsors.height = +this.value; $('#bsHeightTxt').text(this.value + ' px'); save(); });
    $('#bsStrip').on('change', function () { cfg.sponsors.strip = this.checked; save(); });

    $('#bpType').on('change', schedule);
    $('#bpFormat').on('click', '[data-format]', function () {
        format = this.dataset.format;
        $('#bpFormat [data-format]').removeClass('active').filter(this).addClass('active');
        schedule();
    });
    $('#bReset').on('click', async () => {
        await store.removeBrandImage('logo'); await store.removeBrandImage('sponsors');
        cfg = JSON.parse(JSON.stringify(store.BRAND_DEFAULTS));
        store.brand(cfg);
        await loadImgs(); syncForm(); schedule();
        MD.toast('Značka vrátená na predvolené', 'info');
    });

    loadImgs().then(() => { syncForm(); preview(); });
})(jQuery);
