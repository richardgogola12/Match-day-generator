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

    /** Jediné predvolené písmo obrázkov aj stránky */
    MD.FONT = 'Barlow Condensed';
    MD.FONTS = [
        { value: 'Barlow Condensed', label: 'Barlow Condensed (predvolené)', group: 'Predvolené' },
        { value: 'Biome', label: 'Biome (iba ak je nahraté na serveri)', group: 'Predvolené' },
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

    // Google Fonts sa načítajú až vtedy, keď ich šablóna naozaj používa (rýchlosť + žiadne zbytočné spojenia na Google)
    const googleCss = {};
    MD.loadGoogleCss = function (name) {
        const g = GOOGLE.find(([n]) => n === name);
        if (!g) return Promise.resolve();
        if (!googleCss[name]) googleCss[name] = new Promise((resolve) => {
            const link = document.createElement('link');
            link.rel = 'stylesheet';
            link.href = 'https://fonts.googleapis.com/css2?family=' + name.replace(/ /g, '+') + (g[2] ? ':wght@' + g[2] : '') + '&display=swap';
            link.onload = link.onerror = () => resolve();
            document.head.appendChild(link);
            setTimeout(resolve, 3000);
        });
        return googleCss[name];
    };

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
        ['{team}', 'družstvo (zostava, súpiska)'], ['{opponent}', 'súper (zostava)'], ['{category}', 'kategória (Muži A)'],
        ['{season}', 'sezóna'], ['{coach}', 'tréner'], ['{players}', 'počet hráčov'],
        ['{halftime}', 'polčas'], ['{spectators}', 'diváci'], ['{referee}', 'rozhodca'], ['{venue}', 'štadión'],
        ['{player}', 'meno hráča'], ['{playerFirst}', 'krstné meno'], ['{playerLast}', 'priezvisko'], ['{playerNr}', 'číslo hráča'],
        ['{apps}', 'zápasy hráča'], ['{starts}', 'zápasy v základe'], ['{minutes}', 'odohrané minúty'], ['{goalsP}', 'góly hráča'], ['{penalties}', 'góly z penalty'],
        ['{yellow}', 'žlté karty'], ['{red}', 'červené karty'],
        ['{milestone}', 'jubileum (číslo)'], ['{milestoneLabel}', 'jubileum (text)'],
        ['{matchHome}', 'zápas – domáci'], ['{matchAway}', 'zápas – hostia'], ['{matchScore}', 'zápas – skóre'], ['{matchGoals}', 'góly hráča v zápase'],
        ['{roundTitle}', 'PROGRAM / VÝSLEDKY'], ['{afterRound}', 'po 13. kole'],
        ['{h2hPlayed}', 'počet vzájomných zápasov'], ['{weekendTitle}', 'PROGRAM / VÝSLEDKY VÍKENDU'], ['{weekendRange}', 'dátumy víkendu'],
        ['{tpos}', 'miesto v tabuľke'], ['{tpts}', 'body'], ['{tp}', 'zápasy'], ['{tw}', 'výhry'], ['{td}', 'remízy'], ['{tl}', 'prehry'], ['{tgf}', 'strelené'], ['{tga}', 'inkasované'],
    ];

    /* ------------------------------------------------------------------ */
    /* Písmo                                                               */
    /* ------------------------------------------------------------------ */
    const FONT_SAMPLE = 'AaÁáČčĎďÉéÍíĽľĹĺŇňÓóÔôŔŕŠšŤťÚúÝýŽž0123456789';
    let basePromise = null;
    const loadedFamilies = {};

    /** Predvolené písmo (Barlow Condensed z assets/fonts) + Biome, ak je na serveri, + vlastné nahraté písma */
    function loadBaseFonts() {
        if (basePromise) return basePromise;
        const tasks = [];
        if ('FontFace' in window) {
            [['semibold', 'normal', '400'], ['extrabold', 'normal', '700'], ['semibolditalic', 'italic', '400'], ['blackitalic', 'italic', '700']].forEach(([f, style, weight]) => {
                const face = new FontFace(MD.FONT, 'url("' + MD.assetUrl('fonts/barlow-condensed-' + f + '.ttf') + '")', { style, weight });
                tasks.push(face.load().then((x) => document.fonts.add(x)).catch((e) => console.warn('Písmo ' + MD.FONT + ' sa nenačítalo', e)));
            });
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
    let biomePromise = null;
    MD.loadFamily = function (family) {
        if (!family || !document.fonts || !document.fonts.load) return Promise.resolve();
        // Biome (komerčné) iba na požiadanie – ak ho správca nahral na server
        if (family === 'Biome' && 'FontFace' in window) {
            biomePromise = biomePromise || new FontFace('Biome', 'url("' + MD.assetUrl('fonts/biome-w04-regular.otf') + '")').load().then((x) => document.fonts.add(x)).catch(() => null);
            return biomePromise;
        }
        if (!loadedFamilies[family]) {
            loadedFamilies[family] = MD.loadGoogleCss(family).then(() => Promise.all(['400', '700', 'italic 400'].map((w) =>
                document.fonts.load(w + ' 48px "' + family + '"', FONT_SAMPLE).catch(() => null)
            )));
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
            let tries = 0;
            img.onload = () => resolve(img);
            // logo zo servera občas zlyhá (pomalý Sportnet) – ešte dva pokusy, potom bez loga
            img.onerror = () => {
                if (++tries > 2 || /^(data|blob):/.test(src)) return resolve(null);
                setTimeout(() => { img.src = src + (src.includes('?') ? '&' : '?') + 'r=' + tries + Date.now(); }, 400 * tries + Math.random() * 400);
            };
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
            team: data.team || '', opponent: data.opponent || '', category: data.category || '',
            season: data.season || '', coach: data.coach || '',
            players: String(((data.players || {}).squad || []).length || ((data.players || {}).start || []).length || ''),
            halftime: (data.report || {}).halftime || '', spectators: (data.report || {}).spectators || '',
            referee: (data.report || {}).referee || '', venue: (data.report || {}).venue || '',
            h2hPlayed: String((data.h2h || {}).played ?? ''),
            weekendTitle: (data.weekend || {}).title || '', weekendRange: (data.weekend || {}).range || '',
        }, playerTokens(data), teamTokens(data), dateParts(data.startDate));
    };

    function playerTokens(data) {
        const p = data.player || {};
        const name = String(p.name || '');
        const parts = name.split(/\s+/);
        const m = data.playerMatch || {};
        const ms = data.milestone || {};
        const n = (v) => (v === undefined || v === null ? '' : String(v));
        return {
            player: name, playerFirst: parts.length > 1 ? parts.slice(0, -1).join(' ') : name, playerLast: parts.length > 1 ? parts[parts.length - 1] : '',
            playerNr: n(p.nr), apps: n(p.apps), starts: n(p.starts), minutes: n(p.minutes), goalsP: n(p.goals), penalties: n(p.penalties), yellow: n(p.yellow), red: n(p.red),
            milestone: n(ms.value), milestoneLabel: n(ms.label),
            age: n((data.birthday || {}).age), birthdayWish: n((data.birthday || {}).wish),
            monthTitle: n((data.month || {}).title), monthPeriod: n((data.month || {}).period), monthPlayer: n((data.month || {}).player),
            monthValue: n((data.month || {}).value), monthUnit: n((data.month || {}).unit), monthNr: n((data.month || {}).nr),
            matchHome: n(m.home), matchAway: n(m.away), matchScore: n(m.score), matchGoals: n(m.goals),
        };
    }

    function teamTokens(data) {
        const own = (data.table || []).find((r) => r.own) || {};
        const r = data.roundInfo || {};
        const n = (v) => (v === undefined || v === null ? '' : String(v));
        return {
            roundTitle: r.title || '', afterRound: data.afterRound || '',
            tpos: n(own.pos), tpts: n(own.pts), tp: n(own.p), tw: n(own.w), td: n(own.d), tl: n(own.l), tgf: n(own.gf), tga: n(own.ga),
        };
    }

    MD.fillTokens = (text, tokens) => String(text || '').replace(/\{(\w+)\}/g, (m, k) => (k in tokens ? tokens[k] : m));

    /* ------------------------------------------------------------------ */
    /* Pomocné kreslenie                                                   */
    /* ------------------------------------------------------------------ */
    function fontString(el, size) {
        return (el.italic ? 'italic ' : '') + (el.bold ? 'bold ' : '') + size + 'px "' + (el.font || MD.FONT) + '", sans-serif';
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
            // vlastná farba tieňa = ostrý posunutý tieň (štýl nadpisov), inak mäkký čierny
            const hard = !!el.shadowColor;
            ctx.shadowColor = el.shadowColor || 'rgba(0,0,0,.65)';
            ctx.shadowBlur = hard ? (el.shadowBlur ?? 0) : Math.max(4, size * 0.12);
            ctx.shadowOffsetX = hard ? (el.shadowX ?? 0) : Math.max(1, size * 0.03);
            ctx.shadowOffsetY = hard ? (el.shadowY ?? Math.round(size * 0.035)) : Math.max(1, size * 0.04);
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
        const box = boxFromLines(ctx, el, lines, lh, el.size);
        if (!str.trim() && !el.boxColor) return box;
        ctx.save();
        if (el.rotate) { ctx.translate(el.x, el.y); ctx.rotate(el.rotate * Math.PI / 180); ctx.translate(-el.x, -el.y); }
        // podklad ("štítok") za textom
        if (el.boxColor) {
            const px = el.boxPadX ?? Math.round(el.size * 0.35), py = el.boxPadY ?? Math.round(el.size * 0.12);
            const r = el.boxRadius ?? Math.round(el.size * 0.22);
            ctx.save();
            ctx.globalAlpha = el.opacity ?? 1;
            if (el.boxGlow) { ctx.shadowColor = el.boxColor; ctx.shadowBlur = Math.round(el.size * 0.6); }
            ctx.beginPath();
            const bx = box.x - px, by = box.y - py, bw = box.w + px * 2, bh = box.h + py * 2;
            if (ctx.roundRect) ctx.roundRect(bx, by, bw, bh, r); else ctx.rect(bx, by, bw, bh);
            ctx.fillStyle = el.boxColor;
            ctx.fill();
            if (el.boxBorder) { ctx.shadowColor = 'transparent'; ctx.lineWidth = el.boxBorder; ctx.strokeStyle = el.boxBorderColor || '#fff'; ctx.stroke(); }
            ctx.restore();
        }
        ctx.fillStyle = el.color || '#fff';
        withEffects(ctx, el, el.size, () => drawLines(ctx, el, lines, el.x, el.y, lh, el.size));
        ctx.restore();
        applyTextStyle(ctx, el, el.size);
        return box;
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
            rows.push({ lines, y, own: g.own, card: g.card });
            y += (lines.length - 1) * lh;
        });
        return { size, lh, rows, bottom: rows.length ? y + desc : el.y };
    }

    function drawGoals(ctx, el, data, opts) {
        let goals = el.events === 'cards'
            ? (data.cards || []).filter((g) => g.side === el.side).map((c) => Object.assign({}, c, { card: c.kind }))
            : (data.goals || []).filter((g) => g.side === el.side);
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
                    let lx = el.x;
                    if (row.card && i === 0) {
                        // farebná kartička pred menom
                        const ch = layout.size * 0.8, cw = ch * 0.68;
                        const bx = el.align === 'right' ? el.x - ctx.measureText(line).width - cw - 8 : el.align === 'center' ? el.x - ctx.measureText(line).width / 2 - cw - 8 : el.x;
                        ctx.save();
                        ctx.fillStyle = row.card === 'red' ? '#dc2626' : '#facc15';
                        ctx.fillRect(bx, ly - ch * 0.85, cw, ch);
                        ctx.restore();
                        if (el.align !== 'right' && el.align !== 'center') lx = el.x + cw + 8;
                    }
                    drawLines(ctx, el, [line], lx, ly, 0, layout.size);
                });
            });
        });
        const w = el.maxWidth || 260;
        const left = el.align === 'center' ? el.x - w / 2 : el.align === 'right' ? el.x - w : el.x;
        const top = el.y - asc;
        const bottom = Math.max(layout.bottom, opts.editor ? top + layout.size * 3 : top);
        return { x: left, y: top, w, h: Math.min(bottom, el.maxY || bottom) - top, maxY: el.maxY, scale: layout.size / el.size };
    }

    /* ------------------------------------------------------------------ */
    /* Zoznam hráčov (zostava / náhradníci / súpiska) – v stĺpcoch          */
    /* ------------------------------------------------------------------ */
    function playerLine(el, p) {
        const name = (p.name || '') + (p.captain && el.capMark ? el.capMark : '') + (p.gk && el.gkMark ? el.gkMark : '');
        const map = { nr: p.nr ?? '', name, apps: p.apps ?? '', starts: p.starts ?? '', minutes: p.minutes ?? '', goals: p.goals ?? '', penalties: p.penalties ?? '', yellow: p.yellow ?? '', red: p.red ?? '', pos: p.pos ?? '', value: p.value ?? '' };
        let full = String(el.format || '{nr}  {name}').replace(/\{(nr|name|apps|starts|minutes|goals|penalties|yellow|red|pos|value)\}/g, (m, k) => map[k]);
        full = full.replace(/^\s+/, '');
        // číslo zvlášť (iná farba) – iba ak formát začína {nr}
        let num = '';
        if (el.numColor && /^\{nr\}/.test(el.format || '') && map.nr !== '') {
            num = String(map.nr);
            full = full.slice(num.length);
        }
        if (el.uppercase) { full = full.toLocaleUpperCase('sk'); }
        return { num, rest: full };
    }

    function layoutPlayers(ctx, el, list, scale) {
        const size = Math.max(6, Math.round(el.size * scale));
        applyTextStyle(ctx, el, size);
        const cols = Math.max(1, Math.round(el.columns || 1));
        const rows = Math.max(1, Math.ceil(list.length / cols));
        const gap = (el.gap || size * 1.5) * scale;
        const colW = el.maxWidth || 260;
        const colGap = el.colGap || 0;
        const { asc, desc } = metrics(ctx, size);
        const items = list.map((p, i) => {
            const c = Math.floor(i / rows), r = i % rows;
            return { p, x: el.x + c * (colW + colGap), y: el.y + r * gap };
        });
        const bottom = list.length ? el.y + (Math.min(rows, list.length) - 1) * gap + desc : el.y;
        return { size, items, bottom, asc, colW, cols: Math.min(cols, Math.max(1, Math.ceil(list.length / rows))) };
    }

    function fitText(ctx, txt, maxW) {
        if (!maxW || ctx.measureText(txt).width <= maxW) return txt;
        let t = txt;
        while (t.length > 1 && ctx.measureText(t + '…').width > maxW) t = t.slice(0, -1);
        return t.trimEnd() + '…';
    }

    function drawPlayers(ctx, el, data, opts) {
        const all = (data.players || {})[el.source] || [];
        let list = all.filter((p) => p && p.hidden !== true);
        if (el.limit > 0) list = list.slice(0, el.limit);
        let layout = layoutPlayers(ctx, el, list, 1);
        if (el.autoFit && el.maxY) {
            const min = el.minScale || 0.5;
            for (let s = 0.95; layout.bottom > el.maxY && s >= min - 1e-6; s -= 0.05) layout = layoutPlayers(ctx, el, list, s);
        }
        const size = layout.size;
        applyTextStyle(ctx, el, size);
        ctx.textAlign = 'left';
        const colW = layout.colW;
        withEffects(ctx, el, size, () => {
            if (!list.length && el.emptyText) {
                ctx.fillStyle = el.color || '#fff';
                drawLines(ctx, el, [el.emptyText], el.x, el.y, 0, size);
                return;
            }
            layout.items.forEach(({ p, x, y }) => {
                if (el.maxY && y > el.maxY) return;
                const { num, rest } = playerLine(el, p);
                let cx = x;
                if (num) {
                    ctx.fillStyle = el.numColor;
                    drawLines(ctx, el, [num], cx, y, 0, size);
                    cx += ctx.measureText(num).width;
                }
                ctx.fillStyle = el.color || '#fff';
                drawLines(ctx, el, [fitText(ctx, rest, colW - (cx - x))], cx, y, 0, size);
            });
        });
        const cols = Math.max(1, Math.round(el.columns || 1));
        const w = cols * colW + (cols - 1) * (el.colGap || 0);
        const top = el.y - layout.asc;
        const bottom = Math.max(layout.bottom, opts.editor ? top + size * 3 : top);
        return { x: el.x, y: top, w, h: Math.min(bottom, el.maxY || bottom) - top, maxY: el.maxY, scale: size / el.size };
    }

    /* ------------------------------------------------------------------ */
    /* Fotka hráča (kruh / obdĺžnik); bez fotky číslo dresu v kruhu         */
    /* ------------------------------------------------------------------ */
    function shapePath(ctx, el) {
        ctx.beginPath();
        if (el.shape === 'circle') ctx.ellipse(el.x + el.w / 2, el.y + el.h / 2, el.w / 2, el.h / 2, 0, 0, Math.PI * 2);
        else if (ctx.roundRect) ctx.roundRect(el.x, el.y, el.w, el.h, Math.min(el.radius || 0, el.w / 2, el.h / 2));
        else ctx.rect(el.x, el.y, el.w, el.h);
    }

    function drawPhoto(ctx, el, data, assets, opts, tokens) {
        const img = assets.photo;
        ctx.save();
        ctx.globalAlpha = el.opacity ?? 1;
        if (el.shadow) { ctx.shadowColor = 'rgba(0,0,0,.6)'; ctx.shadowBlur = 30; ctx.shadowOffsetY = 10; }
        if (img) {
            if (el.shape !== 'none') {
                shapePath(ctx, el);
                ctx.fillStyle = el.bgColor || '#0b2a1a';
                ctx.fill();
                ctx.shadowColor = 'transparent';
                ctx.save();
                shapePath(ctx, el);
                ctx.clip();
            }
            // "cover" – vyplní celý tvar, zarovnané hore (tvár)
            const r = el.fit === 'contain' ? Math.min(el.w / img.naturalWidth, el.h / img.naturalHeight) : Math.max(el.w / img.naturalWidth, el.h / img.naturalHeight);
            const w = img.naturalWidth * r, h = img.naturalHeight * r;
            ctx.drawImage(img, el.x + (el.w - w) / 2, el.fit === 'contain' ? el.y + (el.h - h) / 2 : el.y, w, h);
            if (el.shape !== 'none') ctx.restore();
        } else if (el.shape !== 'none' || opts.editor) {
            shapePath(ctx, el);
            const g = ctx.createLinearGradient(el.x, el.y, el.x + el.w, el.y + el.h);
            g.addColorStop(0, el.fallbackColor || '#e63946');
            g.addColorStop(1, el.fallbackColor2 || '#8d0f1a');
            ctx.fillStyle = g;
            ctx.fill();
            ctx.shadowColor = 'transparent';
            const txt = MD.fillTokens(el.fallbackText ?? '{playerNr}', tokens);
            if (txt) {
                const size = Math.round(Math.min(el.w, el.h) * 0.45);
                ctx.font = size + 'px "' + (el.font || MD.FONT) + '", sans-serif';
                ctx.textAlign = 'center';
                ctx.textBaseline = 'middle';
                ctx.fillStyle = el.color || '#fff';
                ctx.fillText(txt, el.x + el.w / 2, el.y + el.h / 2 + size * 0.05);
            }
        }
        ctx.restore();
        if (el.borderWidth > 0 && el.shape !== 'none') {
            ctx.save();
            shapePath(ctx, el);
            ctx.lineWidth = el.borderWidth;
            ctx.strokeStyle = el.borderColor || '#fff';
            ctx.stroke();
            ctx.restore();
        }
        return { x: el.x, y: el.y, w: el.w, h: el.h };
    }

    /* ------------------------------------------------------------------ */
    /* Tabuľka súťaže                                                      */
    /* ------------------------------------------------------------------ */
    function drawTable(ctx, el, data, assets, opts) {
        let rows = (data.table || []).slice(0, el.maxRows || 99);
        const n = Math.max(1, rows.length + (el.header ? 1 : 0));
        let rowH = el.rowH || 40;
        if (el.autoFit && el.maxY && el.y + n * rowH > el.maxY) rowH = Math.max(14, (el.maxY - el.y) / n);
        const size = Math.max(6, Math.round(Math.min(el.size || 22, rowH * 0.55)));
        const W = el.w || 700;
        const cols = String(el.columns || 'p,w,d,l,score,pts').split(',').map((c) => c.trim()).filter(Boolean);
        const colW = Math.round(size * 2.3);
        const scoreW = Math.round(size * 3.6);
        const numW = cols.reduce((a, c) => a + (c === 'score' ? scoreW : colW), 0);
        const logoS = el.showLogos ? Math.round(rowH * 0.72) : 0;
        const posW = Math.round(size * 2);
        const nameX = el.x + posW + (logoS ? logoS + 10 : 0);
        const nameW = W - (nameX - el.x) - numW - 8;
        const HEAD = { p: 'Z', w: 'V', d: 'R', l: 'P', score: 'Skóre', pts: 'B', gd: '+/-' };
        const val = (r, c) => (c === 'score' ? r.gf + ':' + r.ga : c === 'gd' ? (r.gf - r.ga > 0 ? '+' : '') + (r.gf - r.ga) : r[c]);
        ctx.save();
        ctx.globalAlpha = el.opacity ?? 1;
        let y = el.y;
        const line = (r, isHead) => {
            const mid = y + rowH / 2;
            if (!isHead && r.own && el.highlight) {
                ctx.fillStyle = el.highlight;
                ctx.beginPath();
                if (ctx.roundRect) ctx.roundRect(el.x - 8, y + 2, W + 16, rowH - 4, 8); else ctx.rect(el.x - 8, y + 2, W + 16, rowH - 4);
                ctx.fill();
            } else if (!isHead && el.stripe && (r.pos % 2 === 0)) {
                ctx.fillStyle = el.stripe;
                ctx.fillRect(el.x - 8, y + 2, W + 16, rowH - 4);
            }
            ctx.textBaseline = 'middle';
            const f = (bold) => (bold ? 'bold ' : '') + size + 'px "' + (el.font || MD.FONT) + '", sans-serif';
            const color = isHead ? (el.headColor || '#ffd166') : (r.own && el.ownColor ? el.ownColor : (el.color || '#fff'));
            ctx.fillStyle = color;
            ctx.font = f(false);
            ctx.textAlign = 'right';
            if (!isHead) ctx.fillText(r.pos + '.', el.x + posW - 8, mid);
            if (!isHead && logoS) {
                const img = assets.teamLogos && assets.teamLogos[r.logo];
                if (img) {
                    const k = Math.min(logoS / img.naturalWidth, logoS / img.naturalHeight);
                    ctx.drawImage(img, el.x + posW + (logoS - img.naturalWidth * k) / 2, mid - img.naturalHeight * k / 2, img.naturalWidth * k, img.naturalHeight * k);
                }
            }
            ctx.textAlign = 'left';
            ctx.font = f(!isHead && r.own && el.ownBold);
            ctx.fillText(isHead ? (el.headName || 'Družstvo') : fitText(ctx, el.uppercase ? r.name.toLocaleUpperCase('sk') : r.name, nameW), nameX, mid);
            ctx.textAlign = 'center';
            let cx = el.x + W - numW;
            cols.forEach((c) => {
                const w = c === 'score' ? scoreW : colW;
                ctx.font = f(c === 'pts' && !isHead);
                ctx.fillStyle = isHead ? color : (c === 'pts' && el.ptsColor ? el.ptsColor : color);
                ctx.fillText(isHead ? HEAD[c] || c : String(val(r, c)), cx + w / 2, mid);
                cx += w;
            });
            y += rowH;
        };
        if (el.header) line({}, true);
        rows.forEach((r) => line(r, false));
        if (!rows.length && opts.editor) { ctx.fillStyle = 'rgba(255,255,255,.5)'; ctx.font = size + 'px sans-serif'; ctx.fillText('Tabuľka', el.x, el.y + rowH / 2); y += rowH; }
        ctx.restore();
        return { x: el.x - 8, y: el.y, w: W + 16, h: Math.max(rowH, y - el.y), maxY: el.maxY };
    }

    /* ------------------------------------------------------------------ */
    /* Zápasy kola (program / výsledky)                                    */
    /* ------------------------------------------------------------------ */
    function drawFixtures(ctx, el, data, assets, opts) {
        const list = (data.fixtures || []).slice(0, el.maxRows || 99);
        let rowH = el.rowH || 70;
        if (el.autoFit && el.maxY && el.y + list.length * rowH > el.maxY) rowH = Math.max(18, (el.maxY - el.y) / Math.max(1, list.length));
        const size = Math.max(6, Math.round(Math.min(el.size || 24, rowH * 0.42)));
        const W = el.w || 720;
        const cx = el.x + W / 2;
        const pillW = Math.round(size * 3.8), pillH = Math.round(size * 1.6);
        const logoS = el.showLogos ? Math.round(Math.min(rowH * 0.7, size * 2)) : 0;
        const sideW = W / 2 - pillW / 2 - (logoS ? logoS + 12 : 0) - 12;
        ctx.save();
        ctx.globalAlpha = el.opacity ?? 1;
        list.forEach((m, i) => {
            const mid = el.y + i * rowH + rowH / 2;
            if (m.own && el.highlight) {
                ctx.fillStyle = el.highlight;
                ctx.beginPath();
                if (ctx.roundRect) ctx.roundRect(el.x - 8, mid - rowH / 2 + 3, W + 16, rowH - 6, 10); else ctx.rect(el.x - 8, mid - rowH / 2 + 3, W + 16, rowH - 6);
                ctx.fill();
            }
            ctx.textBaseline = 'middle';
            ctx.font = size + 'px "' + (el.font || MD.FONT) + '", sans-serif';
            ctx.fillStyle = el.color || '#fff';
            const name = (s) => fitText(ctx, el.uppercase ? s.toLocaleUpperCase('sk') : s, sideW);
            ctx.textAlign = 'right';
            ctx.fillText(name(m.home || ''), cx - pillW / 2 - (logoS ? logoS + 12 : 0) - 10, mid);
            ctx.textAlign = 'left';
            ctx.fillText(name(m.away || ''), cx + pillW / 2 + (logoS ? logoS + 12 : 0) + 10, mid);
            if (logoS) {
                [[m.homeLogo, cx - pillW / 2 - 8 - logoS], [m.awayLogo, cx + pillW / 2 + 8]].forEach(([u, lx]) => {
                    const img = assets.teamLogos && assets.teamLogos[u];
                    if (!img) return;
                    const k = Math.min(logoS / img.naturalWidth, logoS / img.naturalHeight);
                    ctx.drawImage(img, lx + (logoS - img.naturalWidth * k) / 2, mid - img.naturalHeight * k / 2, img.naturalWidth * k, img.naturalHeight * k);
                });
            }
            ctx.fillStyle = m.played ? (el.pillColor || '#e63946') : (el.pillColor2 || 'rgba(255,255,255,.14)');
            ctx.beginPath();
            if (ctx.roundRect) ctx.roundRect(cx - pillW / 2, mid - pillH / 2, pillW, pillH, pillH / 2); else ctx.rect(cx - pillW / 2, mid - pillH / 2, pillW, pillH);
            ctx.fill();
            ctx.fillStyle = el.pillText || '#fff';
            ctx.textAlign = 'center';
            ctx.font = (m.played ? 'bold ' : '') + Math.round(size * (m.played ? 1 : 0.8)) + 'px "' + (el.font || MD.FONT) + '", sans-serif';
            ctx.fillText(m.played ? m.hs + ':' + m.as : (el.showDate && m.day ? m.day + ' ' : '') + (m.time || '–'), cx, mid + 1);
            // družstvo (kategória) nad výsledkom – obrázok Víkend klubu
            if (el.showLabel && m.label) {
                ctx.font = 'bold ' + Math.round(size * 0.58) + 'px "' + (el.font || MD.FONT) + '", sans-serif';
                ctx.fillStyle = el.labelColor || '#ffd166';
                ctx.textBaseline = 'alphabetic';
                ctx.fillText((el.uppercase === false ? m.label : String(m.label).toLocaleUpperCase('sk')), cx, mid - pillH / 2 - 4);
                ctx.textBaseline = 'middle';
            }
        });
        ctx.restore();
        return { x: el.x - 8, y: el.y, w: W + 16, h: Math.max(rowH, list.length * rowH), maxY: el.maxY };
    }

    /* ------------------------------------------------------------------ */
    /* Porovnanie dvoch tímov (vzájomné zápasy) – hodnota | popis | hodnota + pruhy */
    /* ------------------------------------------------------------------ */
    function drawCompare(ctx, el, data, opts) {
        let rows = ((data.h2h || {}).rows || []);
        if (!rows.length && opts.editor) rows = MD.SAMPLE.data.h2h.rows;
        const W = el.w || 700, rowH = el.rowH || 56;
        const size = el.size || 22;
        const barH = el.barH || 8;
        const f = (bold) => (bold ? 'bold ' : '') + size + 'px "' + (el.font || MD.FONT) + '", sans-serif';
        ctx.save();
        ctx.globalAlpha = el.opacity ?? 1;
        ctx.textBaseline = 'alphabetic';
        rows.forEach((r, i) => {
            const top = el.y + i * rowH;
            const ty = top + size;
            ctx.font = f(true);
            ctx.fillStyle = el.color || '#fff';
            ctx.textAlign = 'left'; ctx.fillText(String(r.home), el.x, ty);
            ctx.textAlign = 'right'; ctx.fillText(String(r.away), el.x + W, ty);
            ctx.font = f(false);
            ctx.fillStyle = el.labelColor || el.color || '#fff';
            ctx.textAlign = 'center';
            ctx.fillText(el.uppercase ? String(r.label).toLocaleUpperCase('sk') : String(r.label), el.x + W / 2, ty);
            const by = ty + Math.round(size * 0.45);
            const half = (W - 4) / 2;
            const sum = Math.max(1, (+r.home || 0) + (+r.away || 0));
            const bar = (x, w, color) => { ctx.fillStyle = color; ctx.beginPath(); if (ctx.roundRect) ctx.roundRect(x, by, Math.max(0, w), barH, barH / 2); else ctx.rect(x, by, Math.max(0, w), barH); ctx.fill(); };
            bar(el.x, half, el.barBg || 'rgba(255,255,255,.15)');
            bar(el.x + half + 4, half, el.barBg || 'rgba(255,255,255,.15)');
            const hw = half * (+r.home || 0) / sum, aw = half * (+r.away || 0) / sum;
            if (hw > 0) bar(el.x + half - hw, hw, el.barHome || '#e63946');
            if (aw > 0) bar(el.x + half + 4, aw, el.barAway || '#ffd166');
        });
        ctx.restore();
        return { x: el.x, y: el.y, w: W, h: Math.max(rowH, rows.length * rowH) };
    }

    /* ------------------------------------------------------------------ */
    /* Forma – farebné krúžky V / R / P (vycentrované na x)                */
    /* ------------------------------------------------------------------ */
    function drawFormBadges(ctx, el, data, opts) {
        let list = (data.form || []).slice(-(el.max || 5));
        if (!list.length && opts.editor) list = MD.SAMPLE.data.form;
        const S = el.size || 64, gap = el.gap ?? 18;
        const total = list.length * S + Math.max(0, list.length - 1) * gap;
        const x0 = el.x - total / 2;
        ctx.save();
        ctx.globalAlpha = el.opacity ?? 1;
        ctx.textAlign = 'center';
        ctx.textBaseline = 'middle';
        list.forEach((r, i) => {
            const cx = x0 + i * (S + gap) + S / 2, cy = el.y + S / 2;
            ctx.fillStyle = { V: el.winColor || '#22a06b', R: el.drawColor || '#f2a516', P: el.lossColor || '#d93a3a' }[r] || '#888';
            ctx.beginPath(); ctx.arc(cx, cy, S / 2, 0, Math.PI * 2); ctx.fill();
            ctx.fillStyle = el.color || '#fff';
            ctx.font = 'bold ' + Math.round(S * 0.5) + 'px "' + (el.font || MD.FONT) + '", sans-serif';
            ctx.fillText(r, cx, cy + 1);
        });
        ctx.restore();
        return { x: x0, y: el.y, w: Math.max(S, total), h: S };
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
        // farby klubu: predvolené farby šablón (červená / žltá / tmavozelená) sa nahradia vlastnými
        const theme = opts.theme !== undefined ? opts.theme : (MD.store && MD.store.brandColors ? MD.store.brandColors() : null);
        const elements = theme ? tpl.elements.map((el) => MD.themeElement(el, theme)) : tpl.elements;
        elements.forEach((el) => {
            if (el.visible === false) return;
            // podmienené prvky (napr. číslo dresu iba vtedy, keď je nahratá fotka)
            if (el.showIf === 'photo' && !assets.photo && !opts.editor) return;
            if (el.showIf === 'noPhoto' && assets.photo) return;
            ctx.save();
            try {
                if (el.type === 'text') boxes[el.id] = drawText(ctx, el, tokens);
                else if (el.type === 'logo') boxes[el.id] = drawLogo(ctx, el, assets, opts);
                else if (el.type === 'goals') boxes[el.id] = drawGoals(ctx, el, data || {}, opts);
                else if (el.type === 'shape') boxes[el.id] = drawShape(ctx, el);
                else if (el.type === 'players') boxes[el.id] = drawPlayers(ctx, el, data || {}, opts);
                else if (el.type === 'photo') boxes[el.id] = drawPhoto(ctx, el, data || {}, assets, opts, tokens);
                else if (el.type === 'table') boxes[el.id] = drawTable(ctx, el, data || {}, assets, opts);
                else if (el.type === 'fixtures') boxes[el.id] = drawFixtures(ctx, el, data || {}, assets, opts);
                else if (el.type === 'compare') boxes[el.id] = drawCompare(ctx, el, data || {}, opts);
                else if (el.type === 'formbadges') boxes[el.id] = drawFormBadges(ctx, el, data || {}, opts);
            } catch (e) { console.error('Chyba pri kreslení prvku', el, e); }
            ctx.restore();
        });
        return boxes;
    };

    /* ------------------------------------------------------------------ */
    /* Farby klubu                                                         */
    /* ------------------------------------------------------------------ */
    const hexRgb = (h) => { h = String(h || '').replace('#', ''); if (h.length === 3) h = h.replace(/./g, '$&$&'); const n = parseInt(h, 16) || 0; return [(n >> 16) & 255, (n >> 8) & 255, n & 255]; };
    MD.BASE_COLORS = { primary: '#e63946', accent: '#ffd166', base: '#0b2a1a' };
    const COLOR_KEYS = /color|bg|fill|stroke|highlight|stripe|pill|glow|border|shadow/i;
    /** kópia prvku s vymenenými farbami (#e63946 → primárna, #ffd166 → doplnková, #0b2a1a → tmavá) */
    MD.themeElement = function (el, theme) {
        const map = {};
        Object.entries(MD.BASE_COLORS).forEach(([k, v]) => { if (theme[k] && theme[k].toLowerCase() !== v) map[k] = theme[k]; });
        if (!Object.keys(map).length) return el;
        const out = Object.assign({}, el);
        Object.keys(out).forEach((k) => {
            const v = out[k];
            if (typeof v !== 'string' || !COLOR_KEYS.test(k)) return;
            let s = v;
            Object.entries(map).forEach(([name, to]) => {
                const from = MD.BASE_COLORS[name];
                const [r, g, b] = hexRgb(from), [R, G, B] = hexRgb(to);
                s = s.replace(new RegExp(from, 'gi'), to)
                    .replace(new RegExp('rgba\\(\\s*' + r + '\\s*,\\s*' + g + '\\s*,\\s*' + b + '\\s*,', 'g'), 'rgba(' + R + ',' + G + ',' + B + ',');
            });
            out[k] = s;
        });
        // prvky, ktoré majú farbu iba ako predvolenú hodnotu v kóde
        if (out.type === 'fixtures') { if (!out.pillColor && map.primary) out.pillColor = map.primary; if (!out.labelColor && map.accent) out.labelColor = map.accent; }
        if (out.type === 'table' && !out.headColor && map.accent) out.headColor = map.accent;
        return out;
    };

    function rgbHsl(r, g, b) {
        r /= 255; g /= 255; b /= 255;
        const mx = Math.max(r, g, b), mn = Math.min(r, g, b), l = (mx + mn) / 2;
        if (mx === mn) return [0, 0, l];
        const d = mx - mn, s = l > 0.5 ? d / (2 - mx - mn) : d / (mx + mn);
        const h = mx === r ? (g - b) / d + (g < b ? 6 : 0) : mx === g ? (b - r) / d + 2 : (r - g) / d + 4;
        return [h * 60, s, l];
    }
    function hslRgb(h, s, l) {
        h = ((h % 360) + 360) % 360 / 360;
        if (!s) return [l * 255, l * 255, l * 255];
        const q = l < 0.5 ? l * (1 + s) : l + s - l * s, p = 2 * l - q;
        const f = (t) => { t = (t + 1) % 1; return t < 1 / 6 ? p + (q - p) * 6 * t : t < 1 / 2 ? q : t < 2 / 3 ? p + (q - p) * (2 / 3 - t) * 6 : p; };
        return [f(h + 1 / 3) * 255, f(h) * 255, f(h - 1 / 3) * 255];
    }
    const recolorCache = {};
    /**
     * Prefarbí predvolené pozadie podľa farieb klubu – červené, žlté a zelené odtiene
     * sa posunú k zvoleným farbám (zachová sa svetlo, tiene aj textúra). Vracia canvas.
     */
    MD.recolorImage = function (img, theme) {
        if (!img || !theme) return img;
        const key = img.src + '|' + theme.primary + theme.accent + theme.base;
        if (recolorCache[key]) return recolorCache[key];
        const anchors = [['primary', 355, 26], ['accent', 42, 18], ['base', 140, 80]].map(([k, h, win]) => {
            const [sh, ss, sl] = rgbHsl(...hexRgb(MD.BASE_COLORS[k]));
            const [th, ts, tl] = rgbHsl(...hexRgb(theme[k] || MD.BASE_COLORS[k]));
            return { h, win, dh: th - sh, ks: ss ? ts / ss : 1, kl: sl ? tl / sl : 1, gray: ts < 0.08, same: (theme[k] || '').toLowerCase() === MD.BASE_COLORS[k] };
        }).filter((a) => !a.same);
        const c = document.createElement('canvas');
        c.width = img.naturalWidth || img.width; c.height = img.naturalHeight || img.height;
        const ctx = c.getContext('2d');
        ctx.drawImage(img, 0, 0);
        if (anchors.length) {
            let id;
            try { id = ctx.getImageData(0, 0, c.width, c.height); } catch (e) { return img; }
            const d = id.data;
            for (let i = 0; i < d.length; i += 4) {
                const [h, s, l] = rgbHsl(d[i], d[i + 1], d[i + 2]);
                if (s < 0.04) continue;
                const ws = Math.min(1, (s - 0.04) / 0.12); // málo sýte pixely sa menia postupne (bez fľakov)
                for (const a of anchors) {
                    let dist = Math.abs(h - a.h); if (dist > 180) dist = 360 - dist;
                    if (dist > a.win) continue;
                    const w = (dist < a.win * 0.6 ? 1 : 1 - (dist - a.win * 0.6) / (a.win * 0.4)) * ws;
                    // plne prefarbený odtieň, potom plynulý prechod RGB (žiadne dúhové medzistupne)
                    const ns = Math.min(1, a.gray ? s * 0.15 : s * a.ks);
                    const nl = Math.min(1, l * Math.min(a.kl, 3));
                    const [r, g, b] = hslRgb(h + a.dh, ns, nl);
                    d[i] += (r - d[i]) * w; d[i + 1] += (g - d[i + 1]) * w; d[i + 2] += (b - d[i + 2]) * w;
                    break;
                }
            }
            ctx.putImageData(id, 0, 0);
        }
        c.naturalWidth = c.width; c.naturalHeight = c.height; // drawBackground počíta s naturalWidth
        return (recolorCache[key] = c);
    };

    /* ------------------------------------------------------------------ */
    /* ZIP (bez kompresie – PNG je už skomprimované)                       */
    /* ------------------------------------------------------------------ */
    let crcTable = null;
    function crc32(u8) {
        if (!crcTable) { crcTable = new Uint32Array(256); for (let n = 0; n < 256; n++) { let c = n; for (let k = 0; k < 8; k++) c = c & 1 ? 0xEDB88320 ^ (c >>> 1) : c >>> 1; crcTable[n] = c >>> 0; } }
        let c = 0xFFFFFFFF;
        for (let i = 0; i < u8.length; i++) c = crcTable[(c ^ u8[i]) & 255] ^ (c >>> 8);
        return (c ^ 0xFFFFFFFF) >>> 0;
    }
    /** @param {{name: string, data: Uint8Array}[]} files  @returns {Blob} */
    MD.zip = function (files) {
        const enc = new TextEncoder();
        const parts = [], central = [];
        let offset = 0;
        const d = new Date();
        const dosTime = (d.getHours() << 11) | (d.getMinutes() << 5) | (d.getSeconds() >> 1);
        const dosDate = ((d.getFullYear() - 1980) << 9) | ((d.getMonth() + 1) << 5) | d.getDate();
        const used = {};
        files.forEach((f) => {
            let name = f.name;
            if (used[name]) { name = name.replace(/(\.\w+)$/, '-' + (++used[f.name]) + '$1'); } else used[name] = 1;
            const nb = enc.encode(name), crc = crc32(f.data), size = f.data.length;
            const h = new DataView(new ArrayBuffer(30));
            h.setUint32(0, 0x04034b50, true); h.setUint16(4, 20, true); h.setUint16(6, 0x0800, true); h.setUint16(8, 0, true);
            h.setUint16(10, dosTime, true); h.setUint16(12, dosDate, true); h.setUint32(14, crc, true);
            h.setUint32(18, size, true); h.setUint32(22, size, true); h.setUint16(26, nb.length, true); h.setUint16(28, 0, true);
            parts.push(h.buffer, nb, f.data);
            const c = new DataView(new ArrayBuffer(46));
            c.setUint32(0, 0x02014b50, true); c.setUint16(4, 20, true); c.setUint16(6, 20, true); c.setUint16(8, 0x0800, true); c.setUint16(10, 0, true);
            c.setUint16(12, dosTime, true); c.setUint16(14, dosDate, true); c.setUint32(16, crc, true); c.setUint32(20, size, true); c.setUint32(24, size, true);
            c.setUint16(28, nb.length, true); c.setUint32(42, offset, true);
            central.push(c.buffer, nb);
            offset += 30 + nb.length + size;
        });
        const cdSize = central.reduce((s, b) => s + (b.byteLength ?? b.length), 0);
        const e = new DataView(new ArrayBuffer(22));
        e.setUint32(0, 0x06054b50, true); e.setUint16(8, files.length, true); e.setUint16(10, files.length, true);
        e.setUint32(12, cdSize, true); e.setUint32(16, offset, true);
        return new Blob([...parts, ...central, e.buffer], { type: 'application/zip' });
    };

    /* ------------------------------------------------------------------ */
    /* Výsledný obrázok: formát (štvorec / story) + logo klubu a sponzori  */
    /* ------------------------------------------------------------------ */
    function fitInto(ctx, img, x, y, w, h, alpha, align) {
        const iw = img.naturalWidth || img.width, ih = img.naturalHeight || img.height;
        const r = Math.min(w / iw, h / ih);
        const dw = iw * r, dh = ih * r;
        const dx = align === 'left' ? x : align === 'right' ? x + w - dw : x + (w - dw) / 2;
        ctx.globalAlpha = alpha ?? 1;
        ctx.drawImage(img, dx, y + (h - dh) / 2, dw, dh);
        ctx.globalAlpha = 1;
        return { x: dx, y: y + (h - dh) / 2, w: dw, h: dh };
    }
    function drawSponsors(ctx, img, x, y, w, h, strip) {
        if (strip) {
            ctx.fillStyle = '#ffffff';
            ctx.beginPath();
            if (ctx.roundRect) ctx.roundRect(x, y, w, h, 16); else ctx.rect(x, y, w, h);
            ctx.fill();
        }
        fitInto(ctx, img, x + 18, y + 10, w - 36, h - 20);
    }
    function drawBrandLogo(ctx, img, cfg, W, H) {
        const S = cfg.size || 110, m = 34;
        const pos = cfg.pos || 'tr';
        const x = pos.includes('l') ? m : W - m - S;
        const y = pos.includes('t') ? m : H - m - S;
        ctx.save();
        ctx.shadowColor = 'rgba(0,0,0,.45)'; ctx.shadowBlur = 14;
        fitInto(ctx, img, x, y, S, S, cfg.opacity, pos.includes('l') ? 'left' : 'right');
        ctx.restore();
    }

    /**
     * Zloží výsledný obrázok z vykreslenej šablóny (sq):
     *   format 'square' – šablóna + logo klubu a pás sponzorov,
     *   format 'story'  – 1080×1920: rozmazané pozadie, šablóna v strede, logo hore, sponzori dole.
     * @param {HTMLCanvasElement} out
     * @param {HTMLCanvasElement} sq
     * @param {object} o { format, brand: {logo, sponsors, cfg}, teamLogo, title, subtitle, theme }
     */
    MD.compose = function (out, sq, o) {
        o = o || {};
        const brand = o.brand || {};
        const cfg = brand.cfg || {};
        const useLogo = brand.logo && cfg.logo && cfg.logo.on;
        const useSp = brand.sponsors && cfg.sponsors && cfg.sponsors.on;
        if (o.format !== 'story') {
            out.width = sq.width; out.height = sq.height;
            const ctx = out.getContext('2d');
            ctx.drawImage(sq, 0, 0);
            const W = sq.width, H = sq.height;
            let bottom = H;
            if (useSp) {
                const h = Math.round((cfg.sponsors.height || 92) * W / 1000);
                drawSponsors(ctx, brand.sponsors, 30, H - h - 24, W - 60, h, cfg.sponsors.strip !== false);
                bottom = H - h - 24;
            }
            if (useLogo) {
                const lc = Object.assign({}, cfg.logo, { size: Math.round((cfg.logo.size || 110) * W / 1000) });
                drawBrandLogo(ctx, brand.logo, lc, W, lc.pos && lc.pos.includes('b') ? bottom + 34 - 10 : H);
            }
            return out;
        }
        const W = 1080, H = 1920;
        out.width = W; out.height = H;
        const ctx = out.getContext('2d');
        const theme = o.theme || {};
        // pozadie: tmavý trávnik vo farbách klubu, pruhy a žiara (bez rozmazaných textov šablóny)
        const base = theme.base || '#0b2a1a';
        const [br, bgc, bb] = hexRgb(base);
        const shade = (k) => 'rgb(' + Math.round(br * k) + ',' + Math.round(bgc * k) + ',' + Math.round(bb * k) + ')';
        const lg = ctx.createLinearGradient(0, 0, 0, H);
        lg.addColorStop(0, shade(1.45)); lg.addColorStop(0.45, shade(1)); lg.addColorStop(1, shade(0.5));
        ctx.fillStyle = lg; ctx.fillRect(0, 0, W, H);
        ctx.fillStyle = 'rgba(255,255,255,.03)';
        for (let x = 0; x < W; x += 216) ctx.fillRect(x, 0, 108, H);
        const glow = (x, y, r, c) => { const rg = ctx.createRadialGradient(x, y, 0, x, y, r); rg.addColorStop(0, c); rg.addColorStop(1, 'rgba(0,0,0,0)'); ctx.fillStyle = rg; ctx.fillRect(0, 0, W, H); };
        const [pr, pg, pb] = hexRgb(theme.primary || '#e63946'), [ar, ag, ab] = hexRgb(theme.accent || '#ffd166');
        glow(W, 0, 900, 'rgba(' + pr + ',' + pg + ',' + pb + ',.30)');
        glow(0, H, 900, 'rgba(' + ar + ',' + ag + ',' + ab + ',.16)');
        glow(W / 2, H * 0.42, 800, 'rgba(255,255,255,.05)');

        const top = 400, size = W;
        // horná časť: logo klubu (alebo logo družstva) a nadpis
        const logo = useLogo ? brand.logo : o.teamLogo;
        let ty = 120;
        if (logo) { ctx.save(); ctx.shadowColor = 'rgba(0,0,0,.5)'; ctx.shadowBlur = 24; fitInto(ctx, logo, W / 2 - 95, 70, 190, 190); ctx.restore(); ty = 330; }
        if (o.title) {
            ctx.fillStyle = '#fff'; ctx.textAlign = 'center'; ctx.textBaseline = 'alphabetic';
            ctx.font = 'italic bold ' + (logo ? 44 : 64) + 'px "' + MD.FONT + '", sans-serif';
            ctx.fillText(String(o.title).toUpperCase(), W / 2, ty + (logo ? 30 : 110), W - 120);
        }
        // šablóna s tieňom a jemným rámom
        ctx.save();
        ctx.shadowColor = 'rgba(0,0,0,.6)'; ctx.shadowBlur = 50; ctx.shadowOffsetY = 18;
        ctx.drawImage(sq, 0, top, size, size);
        ctx.restore();
        ctx.fillStyle = theme.primary || '#e63946'; ctx.fillRect(0, top - 8, W * 0.55, 8);
        ctx.fillStyle = theme.accent || '#ffd166'; ctx.fillRect(W * 0.55, top - 8, W * 0.2, 8);
        ctx.fillStyle = '#fff'; ctx.fillRect(W * 0.75, top - 8, W * 0.25, 8);
        // dolná časť: sponzori alebo podnadpis
        const by = top + size + 60;
        if (useSp) {
            ctx.fillStyle = 'rgba(255,255,255,.75)'; ctx.textAlign = 'center';
            ctx.font = 'bold 30px "' + MD.FONT + '", sans-serif';
            ctx.fillText('PARTNERI KLUBU', W / 2, by + 40);
            drawSponsors(ctx, brand.sponsors, 60, by + 70, W - 120, 230, cfg.sponsors.strip !== false);
        } else if (o.subtitle) {
            const lines = String(o.subtitle).split('\n').slice(0, 2);
            ctx.textAlign = 'center'; ctx.textBaseline = 'middle';
            ctx.font = 'italic bold 56px "' + MD.FONT + '", sans-serif';
            const tw = Math.min(W - 120, ctx.measureText(lines[0].toUpperCase()).width + 70);
            ctx.save();
            ctx.translate(W / 2, by + 130); ctx.rotate(-0.035);
            ctx.shadowColor = 'rgba(' + ar + ',' + ag + ',' + ab + ',.55)'; ctx.shadowBlur = 30;
            ctx.fillStyle = theme.accent || '#ffd166';
            ctx.beginPath(); if (ctx.roundRect) ctx.roundRect(-tw / 2, -46, tw, 92, 16); else ctx.rect(-tw / 2, -46, tw, 92); ctx.fill();
            ctx.shadowBlur = 0;
            ctx.fillStyle = base;
            ctx.fillText(lines[0].toUpperCase(), 0, 4, tw - 50);
            ctx.restore();
            if (lines[1]) { ctx.fillStyle = 'rgba(255,255,255,.85)'; ctx.font = 'bold 40px "' + MD.FONT + '", sans-serif'; ctx.fillText(lines[1], W / 2, by + 250, W - 120); }
            ctx.textBaseline = 'alphabetic';
        }
        return out;
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
            team: 'FK Slovan Horná Ves', opponent: 'TJ Družstevník Veľké Ludince', category: 'Muži A', season: '2026/2027', coach: 'Jozef Hrubý',
            report: { halftime: '1:1', spectators: '240', referee: 'Ivan Kráľ', venue: 'Štadión Horná Ves' },
            cards: [
                { minute: '23', name: 'Peter Horváth', side: 'home', kind: 'yellow' },
                { minute: '51', name: 'Ján Novák', side: 'away', kind: 'yellow' },
                { minute: '78', name: 'Marek Molnár', side: 'away', kind: 'red' },
            ],
            player: { name: 'Martin Kováč', nr: '7', apps: 13, starts: 12, minutes: 1064, goals: 9, penalties: 2, yellow: 2, red: 0 },
            playerMatch: { home: 'FK Slovan Horná Ves', away: 'TJ Družstevník Veľké Ludince', score: '3:2', goals: 2 },
            milestone: { value: 100, label: 'zápas za FK Slovan Horná Ves' },
            month: { title: 'STRELEC MESIACA', period: 'Október 2026', player: 'Martin Kováč', value: 6, unit: 'gólov', nr: '7' },
            birthday: { age: 25, wish: 'Prajeme veľa zdravia, radosti a gólov!' },
            afterRound: 'po 12. kole',
            h2h: { played: 8, rows: [['VÝHRY', 4, 3], ['REMÍZY', 1, 1], ['PREHRY', 3, 4], ['GÓLY', 14, 11], ['ŽLTÉ KARTY', 12, 15], ['ČERVENÉ KARTY', 1, 0]].map(([label, home, away]) => ({ label, home, away })) },
            form: ['V', 'V', 'R', 'P', 'V'],
            weekend: { title: 'PROGRAM VÍKENDU', range: '31. 10. – 2. 11. 2026' },
            roundInfo: { title: 'VÝSLEDKY KOLA', no: 12 },
            table: [
                ['FK Slovan Horná Ves', 12, 8, 2, 2, 27, 12], ['TJ Sokol Podhradie', 12, 7, 3, 2, 21, 13], ['MFK Považan', 12, 7, 1, 4, 19, 15],
                ['ŠK Kamenec', 12, 6, 2, 4, 18, 16], ['OFK Lipové 1921', 12, 5, 3, 4, 17, 17], ['TJ Družstevník Veľké Ludince', 12, 4, 3, 5, 15, 18],
                ['FC Hrabové', 12, 4, 2, 6, 14, 19], ['TJ Javorina Horná Ves', 12, 3, 4, 5, 13, 16], ['FK Dolná Lúka', 12, 3, 2, 7, 12, 21],
                ['TJ Slovan Brezina', 12, 3, 1, 8, 11, 22], ['ŠK Vŕbové', 12, 2, 3, 7, 10, 20], ['FK Lehota', 12, 1, 4, 7, 9, 18],
            ].map(([name, p, w, d, l, gf, ga], i) => ({ pos: i + 1, name, p, w, d, l, gf, ga, pts: w * 3 + d, own: i === 0, logo: '' })),
            fixtures: [
                ['FK Slovan Horná Ves', 'TJ Družstevník Veľké Ludince', 3, 2], ['TJ Sokol Podhradie', 'MFK Považan', 1, 1], ['ŠK Kamenec', 'OFK Lipové 1921', 2, 0],
                ['FC Hrabové', 'TJ Javorina Horná Ves', 0, 1], ['FK Dolná Lúka', 'TJ Slovan Brezina', 2, 2], ['ŠK Vŕbové', 'FK Lehota', null, null],
            ].map(([home, away, hs, as], i) => ({ home, away, hs, as, played: hs !== null, time: '14:30', day: 'Ne', own: i === 0, homeLogo: '', awayLogo: '', label: ['Muži A', 'Dorast U19', 'Žiaci U15', 'Muži B', 'Ženy', 'Žiaci U13'][i] })),
        },
        logos: { home: sampleLogo('H', '#e63946', '#8d0f1a'), away: sampleLogo('L', '#1d7bf2', '#0b3a8a') },
    };

    const SAMPLE_NAMES = ['Martin Kováč', 'Ján Novák', 'Peter Horváth', 'Tomáš Szabó', 'Lukáš Baláž', 'Marek Molnár', 'Dávid Lacko', 'Róbert Takáč',
        'Samuel Hudec', 'Adam Krajčovič', 'Filip Urban', 'Michal Bartoš', 'Patrik Gašpar', 'Jakub Mráz', 'Richard Kollár', 'Matej Šimko',
        'Erik Vrábel', 'Oliver Kučera', 'Daniel Polák', 'Andrej Bednár', 'Šimon Král', 'Viktor Hronec', 'Matúš Pavlík', 'Roman Čierny',
        'Juraj Kubica', 'Igor Masár', 'Denis Ondruš', 'Sebastián Janík', 'Kristián Mikuš', 'Boris Šťastný', 'Pavol Zelený', 'Rastislav Uhrín'];
    /** Ukážkoví hráči pre editor: n hráčov súpisky, 11 + 7 v zostave */
    MD.samplePlayers = function (n) {
        const nr = [1, 2, 4, 5, 6, 7, 8, 9, 10, 11, 14, 3, 12, 13, 15, 16, 17, 18, 19, 20, 21, 22, 23, 24, 25, 26, 27, 28, 29, 30, 31, 33];
        const mk = (i) => ({ nr: String(nr[i % nr.length]), name: SAMPLE_NAMES[i % SAMPLE_NAMES.length], gk: i === 0 || i === 12, captain: i === 6, apps: 12 - (i % 7), goals: i > 4 && i < 10 ? 9 - i : 0 });
        const squad = Array.from({ length: n || 24 }, (_, i) => mk(i)).sort((a, b) => a.nr - b.nr);
        const all = Array.from({ length: 18 }, (_, i) => Object.assign(mk(i), { goals: [0, 1, 0, 2, 0, 9, 4, 6, 3, 2, 5, 0, 0, 1, 0, 0, 1, 0][i], yellow: [0, 2, 3, 1, 4, 2, 0, 1, 2, 0, 1, 0, 0, 0, 1, 0, 0, 0][i], red: i === 4 ? 1 : 0 }));
        const top = (f, n) => all.filter((p) => p[f] > 0).sort((a, b) => b[f] - a[f]).slice(0, n || 8);
        return {
            start: Array.from({ length: 11 }, (_, i) => mk(i)), subs: Array.from({ length: 7 }, (_, i) => mk(i + 11)), squad,
            scorers: top('goals'), apps: top('apps'),
            month: top('goals').map((p) => Object.assign({}, p, { value: p.goals })), monthRest: top('goals').slice(1, 5).map((p) => Object.assign({}, p, { value: p.goals })),
            cards: all.filter((p) => p.yellow + p.red > 0).sort((a, b) => (b.red * 3 + b.yellow) - (a.red * 3 + a.yellow)).slice(0, 8),
        };
    };
})(window);
