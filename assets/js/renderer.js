/* =====================================================================
 * Match Day – vykresľovanie obrázka do <canvas>
 * Rovnaký kód používa editor (náhľad) aj generátor (výsledný PNG).
 * ===================================================================== */
(function (window) {
    'use strict';

    const MD = window.MD = window.MD || {};

    /* ------------------------------------------------------------------ */
    /* Písma                                                               */
    /*  - Biome: pôvodné písmo projektu (assets/fonts)                     */
    /*  - Google Fonts: bezplatné (OFL), všetky so slovenskou diakritikou  */
    /*  - systémové a vlastné nahraté písma (IndexedDB)                    */
    /* ------------------------------------------------------------------ */
    // [názov, kategória, váhy pre Google Fonts API ('' = len 400)]
    const GOOGLE = [
        ['Anton', 'Športové / úzke', ''],
        ['Bebas Neue', 'Športové / úzke', ''],
        ['Oswald', 'Športové / úzke', '400;700'],
        ['Teko', 'Športové / úzke', '400;700'],
        ['Barlow Condensed', 'Športové / úzke', '400;700;900'],
        ['Saira Condensed', 'Športové / úzke', '400;700;900'],
        ['Big Shoulders Display', 'Športové / úzke', '400;700;900'],
        ['Fjalla One', 'Športové / úzke', ''],
        ['Roboto Condensed', 'Športové / úzke', '400;700'],
        ['Archivo Black', 'Výrazné', ''],
        ['Russo One', 'Výrazné', ''],
        ['Black Ops One', 'Výrazné', ''],
        ['Bungee', 'Výrazné', ''],
        ['Passion One', 'Výrazné', '400;700;900'],
        ['Alfa Slab One', 'Výrazné', ''],
        ['Bangers', 'Výrazné', ''],
        ['Righteous', 'Výrazné', ''],
        ['Montserrat', 'Moderné', '400;700;900'],
        ['Poppins', 'Moderné', '400;700;900'],
        ['Rubik', 'Moderné', '400;700;900'],
        ['Exo 2', 'Moderné', '400;700;900'],
        ['Kanit', 'Moderné', '400;700;900'],
        ['Chakra Petch', 'Moderné', '400;700'],
        ['Rajdhani', 'Moderné', '400;700'],
        ['Lobster', 'Ručne písané', ''],
        ['Pacifico', 'Ručne písané', ''],
    ];

    MD.FONTS = [
        { value: 'Biome', label: 'Biome (pôvodné)', group: 'Pôvodné' },
        ...GOOGLE.map(([name, group]) => ({ value: name, label: name, group, google: true })),
        ...['Arial', 'Verdana', 'Trebuchet MS', 'Impact', 'Georgia', 'Courier New'].map((n) => ({ value: n, label: n, group: 'Systémové' })),
    ];
    MD.customFonts = []; // názvy nahratých písiem

    /** Zoznam písiem zoskupený podľa kategórie (vrátane vlastných) */
    MD.fontGroups = function () {
        const groups = {};
        const all = MD.FONTS.concat(MD.customFonts.map((n) => ({ value: n, label: n, group: 'Vlastné (nahraté)' })));
        all.forEach((f) => { (groups[f.group] = groups[f.group] || []).push(f); });
        return groups;
    };

    // Google Fonts CSS – každé písmo zvlášť, aby chyba jedného nezablokovala ostatné.
    // Samotné súbory písma sa sťahujú až keď sú naozaj potrebné.
    (function injectGoogleFonts() {
        GOOGLE.forEach(([n, , w]) => {
            const link = document.createElement('link');
            link.rel = 'stylesheet';
            link.href = 'https://fonts.googleapis.com/css2?family=' + n.replace(/ /g, '+') + (w ? ':wght@' + w : '') + '&display=swap';
            document.head.appendChild(link);
        });
    })();

    const MONTHS = ['Jan', 'Feb', 'Mar', 'Apr', 'Máj', 'Jún', 'Júl', 'Aug', 'Sep', 'Okt', 'Nov', 'Dec'];
    const MONTHS_LONG = ['Január', 'Február', 'Marec', 'Apríl', 'Máj', 'Jún', 'Júl', 'August', 'September', 'Október', 'November', 'December'];
    const WEEKDAYS = ['Nedeľa', 'Pondelok', 'Utorok', 'Streda', 'Štvrtok', 'Piatok', 'Sobota'];

    /** Zástupné značky, ktoré sa dajú použiť v texte prvku */
    MD.TOKENS = [
        ['{d1name}', 'názov domácich'], ['{d2name}', 'názov hostí'],
        ['{score1}', 'góly domácich'], ['{score2}', 'góly hostí'], ['{score}', 'skóre 2:1'],
        ['{day}', 'deň'], ['{month}', 'mesiac (Okt)'], ['{monthLong}', 'mesiac (Október)'], ['{year}', 'rok'],
        ['{weekday}', 'deň v týždni'], ['{date}', '25.10.2026'], ['{time}', 'čas'],
        ['{competition}', 'súťaž'], ['{round}', 'kolo'], ['{roundLabel}', '„10. Kolo“'],
    ];

    /* ------------------------------------------------------------------ */
    /* Písmo                                                               */
    /* ------------------------------------------------------------------ */
    const FONT_SAMPLE = 'AaÁáČčĎďÉéÍíĽľĹĺŇňÓóÔôŔŕŠšŤťÚúÝýŽž0123456789';
    let basePromise = null;
    const loadedFamilies = {};

    /** Biome + vlastné nahraté písma (raz za načítanie stránky) */
    function loadBaseFonts() {
        if (basePromise) return basePromise;
        const tasks = [];
        if ('FontFace' in window) {
            const face = new FontFace('Biome', 'url("' + MD.assetUrl('fonts/biome-w04-regular.otf') + '")');
            tasks.push(face.load().then((f) => document.fonts.add(f)).catch((e) => console.warn('Font Biome sa nenačítal', e)));
            if (MD.store && MD.store.listFonts) {
                tasks.push(MD.store.listFonts().then((list) => Promise.all(list.map(MD.registerCustomFont))).catch(() => {}));
            }
        }
        return (basePromise = Promise.all(tasks));
    }

    /** Zaregistruje vlastné písmo z Blobu */
    MD.registerCustomFont = function ({ name, blob }) {
        return blob.arrayBuffer()
            .then((buf) => new FontFace(name, buf).load())
            .then((f) => {
                document.fonts.add(f);
                if (!MD.customFonts.includes(name)) MD.customFonts.push(name);
                return name;
            });
    };

    /** Načíta jedno písmo (normálne aj tučné), aby ho canvas vedel použiť */
    MD.loadFamily = function (family) {
        if (!family || !document.fonts || !document.fonts.load) return Promise.resolve();
        if (!loadedFamilies[family]) {
            loadedFamilies[family] = Promise.all(['400', '700', 'italic 400'].map((w) =>
                document.fonts.load(w + ' 48px "' + family + '"', FONT_SAMPLE).catch(() => null)
            ));
        }
        return loadedFamilies[family];
    };

    /**
     * Zabezpečí načítanie písiem pred kreslením.
     * @param {object} [tpl] ak je zadaná šablóna, načítajú sa všetky písma, ktoré používa
     */
    MD.ensureFonts = function (tpl) {
        return loadBaseFonts().then(() => {
            if (!tpl) return;
            const families = [...new Set(tpl.elements.map((e) => e.font).filter(Boolean))];
            // max. 4 s – ak je Google Fonts nedostupné, kreslí sa náhradným písmom
            return Promise.race([
                Promise.all(families.map(MD.loadFamily)),
                new Promise((r) => setTimeout(r, 4000)),
            ]);
        });
    };

    /** Načíta obrázok; pri chybe vráti null (nikdy nezlyhá) */
    MD.loadImage = function (src) {
        return new Promise((resolve) => {
            if (!src) return resolve(null);
            const img = new Image();
            img.decoding = 'async';
            img.onload = () => resolve(img);
            img.onerror = () => resolve(null);
            img.src = src;
        });
    };

    /* ------------------------------------------------------------------ */
    /* Dáta -> zástupné značky                                             */
    /* ------------------------------------------------------------------ */
    function dateParts(iso) {
        const d = iso ? new Date(iso) : null;
        if (!d || isNaN(d)) return { day: '', month: '', monthLong: '', year: '', time: '', weekday: '', date: '' };
        const fmt = new Intl.DateTimeFormat('en-GB', {
            timeZone: 'Europe/Bratislava', year: 'numeric', month: 'numeric', day: 'numeric',
            hour: '2-digit', minute: '2-digit', hour12: false, weekday: 'short',
        });
        const p = {};
        fmt.formatToParts(d).forEach((x) => { p[x.type] = x.value; });
        const wd = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'].indexOf(p.weekday);
        const m = parseInt(p.month, 10) - 1;
        return {
            day: String(parseInt(p.day, 10)).padStart(2, '0'),
            month: MONTHS[m], monthLong: MONTHS_LONG[m], year: p.year,
            time: (p.hour === '24' ? '00' : p.hour) + ':' + p.minute,
            weekday: WEEKDAYS[wd] || '',
            date: parseInt(p.day, 10) + '.' + (m + 1) + '.' + p.year,
        };
    }

    MD.tokens = function (data) {
        const round = String(data.round || '');
        const roundLabel = /kolo/i.test(round) ? round : (round ? round + '. Kolo' : '');
        return Object.assign({
            d1name: data.d1name || '', d2name: data.d2name || '',
            score1: data.score1 ?? '', score2: data.score2 ?? '',
            score: (data.score1 ?? '') + ':' + (data.score2 ?? ''),
            competition: data.competition || '', round, roundLabel,
        }, dateParts(data.startDate));
    };

    MD.fillTokens = (text, tokens) => String(text || '').replace(/\{(\w+)\}/g, (m, k) => (k in tokens ? tokens[k] : m));

    /* ------------------------------------------------------------------ */
    /* Pomocné kreslenie                                                   */
    /* ------------------------------------------------------------------ */
    function fontString(el, size) {
        return (el.italic ? 'italic ' : '') + (el.bold ? 'bold ' : '') + size + 'px "' + (el.font || 'Biome') + '", sans-serif';
    }

    function applyTextStyle(ctx, el, size) {
        ctx.font = fontString(el, size);
        ctx.textAlign = el.align || 'left';
        ctx.textBaseline = 'alphabetic';
        if ('letterSpacing' in ctx) ctx.letterSpacing = (el.letterSpacing || 0) + 'px';
    }

    function metrics(ctx, size) {
        const m = ctx.measureText('ÁÉgjy');
        const asc = m.fontBoundingBoxAscent || m.actualBoundingBoxAscent || size * 0.8;
        const desc = m.fontBoundingBoxDescent || m.actualBoundingBoxDescent || size * 0.2;
        return { asc, desc };
    }

    /** Zalomenie textu podľa šírky v px (0 = bez zalomenia) */
    function wrap(ctx, text, maxWidth) {
        const out = [];
        String(text).split('\n').forEach((para) => {
            if (!maxWidth) { out.push(para); return; }
            const words = para.split(/\s+/).filter(Boolean);
            let line = '';
            words.forEach((w) => {
                const test = line ? line + ' ' + w : w;
                if (ctx.measureText(test).width > maxWidth && line) {
                    out.push(line);
                    line = w;
                } else {
                    line = test;
                }
            });
            out.push(line);
        });
        return out;
    }

    function withEffects(ctx, el, size, draw) {
        ctx.save();
        ctx.globalAlpha = el.opacity ?? 1;
        if (el.shadow) {
            ctx.shadowColor = 'rgba(0,0,0,.65)';
            ctx.shadowBlur = Math.max(4, size * 0.12);
            ctx.shadowOffsetX = Math.max(1, size * 0.03);
            ctx.shadowOffsetY = Math.max(1, size * 0.04);
        }
        draw();
        ctx.restore();
    }

    function drawLines(ctx, el, lines, x, y0, lh, size) {
        lines.forEach((line, i) => {
            const y = y0 + i * lh;
            if (el.strokeWidth > 0) {
                ctx.lineJoin = 'round';
                ctx.lineWidth = el.strokeWidth * 2;
                ctx.strokeStyle = el.strokeColor || '#000';
                ctx.strokeText(line, x, y);
            }
            ctx.fillText(line, x, y);
        });
    }

    function boxFromLines(ctx, el, lines, lh, size, fixedWidth) {
        const { asc, desc } = metrics(ctx, size);
        const w = fixedWidth || Math.max(1, ...lines.map((l) => ctx.measureText(l).width));
        const left = el.align === 'center' ? el.x - w / 2 : el.align === 'right' ? el.x - w : el.x;
        return { x: left, y: el.y - asc, w, h: asc + desc + (lines.length - 1) * lh };
    }

    /* ------------------------------------------------------------------ */
    /* Typy prvkov                                                         */
    /* ------------------------------------------------------------------ */
    function drawText(ctx, el, tokens) {
        let str = MD.fillTokens(el.text, tokens);
        if (el.uppercase) str = str.toLocaleUpperCase('sk');
        applyTextStyle(ctx, el, el.size);
        const lines = wrap(ctx, str, el.maxWidth);
        const lh = el.size * (el.lineHeight || 1.25);
        ctx.fillStyle = el.color || '#fff';
        withEffects(ctx, el, el.size, () => drawLines(ctx, el, lines, el.x, el.y, lh, el.size));
        applyTextStyle(ctx, el, el.size);
        return boxFromLines(ctx, el, lines, lh, el.size);
    }

    function drawLogo(ctx, el, assets, opts) {
        const img = assets.logos && assets.logos[el.team];
        const s = el.size;
        if (img) {
            const r = Math.min(s / img.naturalWidth, s / img.naturalHeight);
            const w = img.naturalWidth * r, h = img.naturalHeight * r;
            withEffects(ctx, el, s * 0.3, () => ctx.drawImage(img, el.x + (s - w) / 2, el.y + (s - h) / 2, w, h));
        } else if (opts.editor) {
            ctx.save();
            ctx.strokeStyle = 'rgba(255,255,255,.7)';
            ctx.setLineDash([8, 6]);
            ctx.lineWidth = 2;
            ctx.beginPath();
            ctx.arc(el.x + s / 2, el.y + s / 2, s / 2 - 2, 0, Math.PI * 2);
            ctx.stroke();
            ctx.restore();
        }
        return { x: el.x, y: el.y, w: s, h: s };
    }

    function drawShape(ctx, el) {
        ctx.save();
        ctx.globalAlpha = el.opacity ?? 0.5;
        ctx.fillStyle = el.color || '#000';
        ctx.beginPath();
        const r = Math.min(el.radius || 0, el.w / 2, el.h / 2);
        if (ctx.roundRect) ctx.roundRect(el.x, el.y, el.w, el.h, r);
        else ctx.rect(el.x, el.y, el.w, el.h);
        ctx.fill();
        ctx.restore();
        return { x: el.x, y: el.y, w: el.w, h: el.h };
    }

    /**
     * Zoznam strelcov – každý gól = jeden záznam, dlhé mená sa zalamujú.
     * autoFit: pri veľa góloch sa písmo aj rozostupy zmenšujú, kým sa zoznam nezmestí nad maxY.
     */
    function layoutGoals(ctx, el, goals, scale) {
        const size = Math.max(6, Math.round(el.size * scale));
        applyTextStyle(ctx, el, size);
        const lh = size * (el.lineHeight || 1.2);
        const gap = el.gap * scale;
        const { desc } = metrics(ctx, size);
        const rows = [];
        let y = el.y;
        goals.forEach((g, i) => {
            let name = (g.own ? (el.ownPrefix || '') : '') + (g.name || '');
            let txt = (el.format || "{min}' {name}").replace('{min}', g.minute ?? '').replace('{name}', name);
            if (el.uppercase) txt = txt.toLocaleUpperCase('sk');
            const lines = wrap(ctx, txt, el.maxWidth);
            if (i > 0) y += gap;
            rows.push({ lines, y, own: g.own });
            y += (lines.length - 1) * lh;
        });
        return { size, lh, rows, bottom: rows.length ? y + desc : el.y };
    }

    function drawGoals(ctx, el, data, opts) {
        let goals = (data.goals || []).filter((g) => g.side === el.side);
        let layout = layoutGoals(ctx, el, goals, 1);
        if (el.autoFit && el.maxY) {
            const min = el.minScale || 0.5;
            for (let s = 0.95; layout.bottom > el.maxY && s >= min - 1e-6; s -= 0.05) {
                layout = layoutGoals(ctx, el, goals, s);
            }
        }
        applyTextStyle(ctx, el, layout.size);
        const { asc } = metrics(ctx, layout.size);
        withEffects(ctx, el, layout.size, () => {
            layout.rows.forEach((row) => {
                ctx.fillStyle = (row.own && el.ownColor) ? el.ownColor : (el.color || '#fff');
                row.lines.forEach((line, i) => {
                    const ly = row.y + i * layout.lh;
                    if (el.maxY && ly > el.maxY) return;
                    drawLines(ctx, el, [line], el.x, ly, 0, layout.size);
                });
            });
        });
        const w = el.maxWidth || 260;
        const left = el.align === 'center' ? el.x - w / 2 : el.align === 'right' ? el.x - w : el.x;
        const top = el.y - asc;
        const bottom = Math.max(layout.bottom, opts.editor ? top + layout.size * 3 : top);
        return { x: left, y: top, w, h: Math.min(bottom, el.maxY || bottom) - top, maxY: el.maxY, scale: layout.size / el.size };
    }

    function drawBackground(ctx, tpl, img) {
        const W = tpl.width, H = tpl.height;
        if (img) {
            const r = Math.max(W / img.naturalWidth, H / img.naturalHeight);
            const w = img.naturalWidth * r, h = img.naturalHeight * r;
            ctx.drawImage(img, (W - w) / 2, (H - h) / 2, w, h);
        } else {
            const g = ctx.createLinearGradient(0, 0, W, H);
            g.addColorStop(0, '#0f2e1d');
            g.addColorStop(1, '#06140c');
            ctx.fillStyle = g;
            ctx.fillRect(0, 0, W, H);
        }
    }

    /**
     * Vykreslí šablónu.
     * @param {HTMLCanvasElement} canvas
     * @param {object} tpl      šablóna (width, height, elements)
     * @param {object} data     dáta zápasu (d1name, d2name, startDate, competition, round, score1, score2, goals[])
     * @param {object} assets   { background: Image|null, logos: { home: Image|null, away: Image|null } }
     * @param {object} opts     { editor: bool }
     * @returns {object}        mapa id prvku -> obdĺžnik {x, y, w, h} (pre editor)
     */
    MD.render = function (canvas, tpl, data, assets, opts) {
        opts = opts || {};
        assets = assets || {};
        if (canvas.width !== tpl.width) canvas.width = tpl.width;
        if (canvas.height !== tpl.height) canvas.height = tpl.height;
        const ctx = canvas.getContext('2d');
        ctx.clearRect(0, 0, tpl.width, tpl.height);
        drawBackground(ctx, tpl, assets.background);

        const tokens = MD.tokens(data || {});
        const boxes = {};
        tpl.elements.forEach((el) => {
            if (el.visible === false) return;
            ctx.save();
            try {
                if (el.type === 'text') boxes[el.id] = drawText(ctx, el, tokens);
                else if (el.type === 'logo') boxes[el.id] = drawLogo(ctx, el, assets, opts);
                else if (el.type === 'goals') boxes[el.id] = drawGoals(ctx, el, data || {}, opts);
                else if (el.type === 'shape') boxes[el.id] = drawShape(ctx, el);
            } catch (e) { console.error('Chyba pri kreslení prvku', el, e); }
            ctx.restore();
        });
        return boxes;
    };

    /* ------------------------------------------------------------------ */
    /* Ukážkové dáta pre editor                                            */
    /* ------------------------------------------------------------------ */
    function sampleLogo(letter, c1, c2) {
        const svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 120 120">' +
            '<defs><linearGradient id="g" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="' + c1 + '"/><stop offset="1" stop-color="' + c2 + '"/></linearGradient></defs>' +
            '<path d="M60 4 L108 20 V58 C108 88 86 106 60 116 C34 106 12 88 12 58 V20 Z" fill="url(#g)" stroke="#fff" stroke-width="5"/>' +
            '<text x="60" y="78" font-family="Arial" font-weight="bold" font-size="46" fill="#fff" text-anchor="middle">' + letter + '</text></svg>';
        return 'data:image/svg+xml;charset=utf-8,' + encodeURIComponent(svg);
    }

    MD.SAMPLE = {
        data: {
            d1name: 'FK Slovan Horná Ves', d2name: 'TJ Družstevník Veľké Ludince',
            startDate: new Date(Date.now() + 3 * 86400000).toISOString().slice(0, 11) + '14:30:00Z',
            competition: 'V. liga Západ', round: '12', score1: 3, score2: 2,
            goals: [
                { minute: '12', name: 'Martin Kováč', side: 'home' },
                { minute: '27', name: 'Ján Novák', side: 'away' },
                { minute: '41', name: 'Peter Horváth', side: 'home' },
                { minute: '58', name: 'Tomáš Szabó-Varga', side: 'away', own: true },
                { minute: '76', name: 'Lukáš Baláž', side: 'home' },
                { minute: '89', name: 'Marek Molnár', side: 'away' },
            ],
        },
        logos: { home: sampleLogo('H', '#e63946', '#8d0f1a'), away: sampleLogo('L', '#1d7bf2', '#0b3a8a') },
    };
})(window);
