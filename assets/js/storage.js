/* =====================================================================
 * Match Day – úložisko nastavení v prehliadači (bez databázy)
 *
 *  - rozloženie šablón (prvky, pozície, písma …)  -> localStorage
 *  - obrázky pozadí (môžu mať niekoľko MB)        -> IndexedDB
 *    (ak IndexedDB nie je dostupná, použije sa localStorage ako dataURL)
 * ===================================================================== */
(function (window) {
    'use strict';

    const MD = window.MD = window.MD || {};
    const BASE = (window.MD_BASE || '').replace(/\/$/, '');
    const LS_KEY = 'matchday.templates.v1';
    const LS_PREFS = 'matchday.prefs.v1';
    const DB_NAME = 'matchday';
    const DB_STORE = 'backgrounds';

    MD.assetUrl = (p) => BASE + '/assets/' + p.replace(/^\//, '');

    /* ------------------------------------------------------------------
     * Predvolené šablóny – zodpovedajú pôvodným súradniciam z GD generátora
     * (veľkosti písma sú v px; GD body × 1.333)
     * ------------------------------------------------------------------ */
    const teamNames = () => ([
        { id: 'name1', type: 'text', label: 'Názov domácich', text: '{d1name}', x: 310, y: 535, size: 24, align: 'center', maxWidth: 260, lineHeight: 1.25 },
        { id: 'name2', type: 'text', label: 'Názov hostí', text: '{d2name}', x: 700, y: 535, size: 24, align: 'center', maxWidth: 260, lineHeight: 1.25 },
        { id: 'logo1', type: 'logo', label: 'Logo domácich', team: 'home', x: 70, y: 440, size: 120 },
        { id: 'logo2', type: 'logo', label: 'Logo hostí', team: 'away', x: 820, y: 440, size: 120 },
    ]);
    const competition = () => ({ id: 'competition', type: 'text', label: 'Súťaž | kolo', text: '{competition} | {roundLabel}', x: 60, y: 940, size: 20, align: 'left', maxWidth: 0 });

    // spoločné prvky šablón Zostava a Súpiska (panel pozadia team-bg.jpg: x 116–884, y 96–932)
    const bigTitle = (text) => ({ id: 'title', type: 'text', label: 'Nadpis', text, x: 500, y: 200, size: 96, align: 'center', color: '#e63946', strokeWidth: 5, strokeColor: '#ffffff', shadow: true, letterSpacing: 3 });
    const yellow = '#ffd166';

    const DEFAULTS = {
        matchday: {
            name: 'Match day',
            icon: 'bi-calendar-event',
            width: 1000, height: 1000,
            defaultBackground: 'img/match-day-bg.png',
            elements: [
                ...teamNames(),
                { id: 'day', type: 'text', label: 'Deň', text: '{day}', x: 260, y: 760, size: 160, align: 'left' },
                { id: 'monthYear', type: 'text', label: 'Mesiac a rok', text: '{month} {year}', x: 470, y: 690, size: 67, align: 'left' },
                { id: 'time', type: 'text', label: 'Čas', text: '{time}', x: 470, y: 770, size: 67, align: 'left' },
                competition(),
            ],
        },
        lineup: {
            name: 'Zostava',
            icon: 'bi-person-lines-fill',
            width: 1000, height: 1000,
            defaultBackground: 'img/team-bg.jpg',
            elements: [
                { id: 'logoTeam', type: 'logo', label: 'Logo družstva', team: 'team', x: 150, y: 118, size: 100 },
                { id: 'logoOpp', type: 'logo', label: 'Logo súpera', team: 'opponent', x: 750, y: 118, size: 100 },
                bigTitle('ZOSTAVA'),
                { id: 'team', type: 'text', label: 'Názov družstva', text: '{team}', x: 500, y: 250, size: 30, align: 'center', color: yellow, maxWidth: 560 },
                { id: 'match', type: 'text', label: 'Zápas', text: '{d1name} – {d2name}', x: 500, y: 292, size: 22, align: 'center', maxWidth: 700, opacity: 0.9 },
                { id: 'when', type: 'text', label: 'Dátum a čas', text: '{weekday} {date} · {time}', x: 500, y: 324, size: 20, align: 'center', opacity: 0.8 },
                { id: 'startLabel', type: 'text', label: 'Nadpis základu', text: 'ZÁKLADNÁ ZOSTAVA', x: 160, y: 385, size: 22, color: yellow, letterSpacing: 2 },
                { id: 'start', type: 'players', label: 'Základná zostava', source: 'start', x: 160, y: 432, size: 28, columns: 2, maxWidth: 330, colGap: 20, gap: 46, maxY: 700 },
                { id: 'subsLabel', type: 'text', label: 'Nadpis náhradníkov', text: 'NÁHRADNÍCI', x: 160, y: 745, size: 22, color: yellow, letterSpacing: 2 },
                { id: 'subs', type: 'players', label: 'Náhradníci', source: 'subs', x: 160, y: 785, size: 20, columns: 3, maxWidth: 215, colGap: 15, gap: 31, maxY: 870 },
                { id: 'coach', type: 'text', label: 'Tréner', text: 'Tréner: {coach}', x: 160, y: 908, size: 19, opacity: 0.85 },
                { id: 'competition', type: 'text', label: 'Súťaž | kolo', text: '{competition} | {roundLabel}', x: 840, y: 908, size: 17, align: 'right', opacity: 0.75 },
            ],
        },
        squad: {
            name: 'Súpiska',
            icon: 'bi-people',
            width: 1000, height: 1000,
            defaultBackground: 'img/team-bg.jpg',
            elements: [
                { id: 'logoTeam', type: 'logo', label: 'Logo vľavo', team: 'team', x: 150, y: 118, size: 100 },
                { id: 'logoTeam2', type: 'logo', label: 'Logo vpravo', team: 'team', x: 750, y: 118, size: 100 },
                bigTitle('SÚPISKA'),
                { id: 'team', type: 'text', label: 'Názov družstva', text: '{team}', x: 500, y: 250, size: 30, align: 'center', color: yellow, maxWidth: 560 },
                { id: 'season', type: 'text', label: 'Kategória a sezóna', text: '{category}  {season}', x: 500, y: 292, size: 22, align: 'center', opacity: 0.9 },
                { id: 'squad', type: 'players', label: 'Hráči', source: 'squad', x: 150, y: 360, size: 23, columns: 3, maxWidth: 225, colGap: 12, gap: 36, maxY: 870, minScale: 0.55 },
                { id: 'coach', type: 'text', label: 'Tréner', text: 'Tréner: {coach}', x: 500, y: 908, size: 21, align: 'center', opacity: 0.9 },
            ],
        },
        result: {
            name: 'Výsledok',
            icon: 'bi-trophy',
            width: 1000, height: 1000,
            defaultBackground: 'img/match-result-bg.png',
            elements: [
                ...teamNames(),
                { id: 'score', type: 'text', label: 'Skóre', text: '{score1}:{score2}', x: 500, y: 765, size: 227, align: 'center' },
                { id: 'day', type: 'text', label: 'Deň', text: '{day}', x: 340, y: 890, size: 107, align: 'left' },
                { id: 'monthYear', type: 'text', label: 'Mesiac a rok', text: '{month} {year}', x: 490, y: 850, size: 40, align: 'left' },
                { id: 'time', type: 'text', label: 'Čas', text: '{time}', x: 490, y: 890, size: 40, align: 'left' },
                competition(),
            ],
        },
        goals: {
            name: 'Strelci',
            icon: 'bi-list-ol',
            width: 1000, height: 1000,
            defaultBackground: 'img/match-result-goals-bg.png',
            elements: [
                ...teamNames(),
                { id: 'score', type: 'text', label: 'Skóre', text: '{score1}:{score2}', x: 500, y: 655, size: 107, align: 'center' },
                { id: 'goalsHome', type: 'goals', label: 'Strelci domácich', side: 'home', x: 130, y: 615, size: 21, gap: 34, lineHeight: 1.2, maxWidth: 270, maxY: 925, align: 'left', autoFit: true, minScale: 0.6 },
                { id: 'goalsAway', type: 'goals', label: 'Strelci hostí', side: 'away', x: 630, y: 615, size: 21, gap: 34, lineHeight: 1.2, maxWidth: 270, maxY: 925, align: 'left', autoFit: true, minScale: 0.6 },
                competition(),
            ],
        },
    };

    /** Spoločné predvolené vlastnosti podľa typu prvku */
    const TYPE_DEFAULTS = {
        text: { text: 'Text', size: 32, font: 'Biome', color: '#ffffff', align: 'left', maxWidth: 0, lineHeight: 1.25, uppercase: false, bold: false, italic: false, letterSpacing: 0, shadow: false, strokeWidth: 0, strokeColor: '#000000', opacity: 1 },
        logo: { team: 'home', size: 120, shadow: false, opacity: 1 },
        goals: { side: 'home', size: 21, font: 'Biome', color: '#ffffff', align: 'left', maxWidth: 270, gap: 34, lineHeight: 1.2, maxY: 925, autoFit: true, minScale: 0.6, format: "{min}' {name}", ownPrefix: '(vl.) ', uppercase: false, bold: false, shadow: false, strokeWidth: 0, strokeColor: '#000000', opacity: 1, ownColor: '' },
        shape: { w: 300, h: 60, color: '#000000', opacity: 0.5, radius: 12 },
        players: { source: 'start', size: 26, font: 'Biome', color: '#ffffff', numColor: '#ffd166', align: 'left', letterSpacing: 0, italic: false, columns: 2, maxWidth: 330, colGap: 20, gap: 44, lineHeight: 1.2, maxY: 900, autoFit: true, minScale: 0.6, format: '{nr}  {name}', capMark: ' (C)', gkMark: ' (B)', emptyText: '', uppercase: false, bold: false, shadow: false, strokeWidth: 0, strokeColor: '#000000', opacity: 1 },
    };
    MD.TYPE_DEFAULTS = TYPE_DEFAULTS;

    MD.TEMPLATE_IDS = ['matchday', 'lineup', 'result', 'goals', 'squad'];

    const clone = (o) => JSON.parse(JSON.stringify(o));

    function normalizeElement(el) {
        return Object.assign({ visible: true }, TYPE_DEFAULTS[el.type] || {}, el);
    }

    function normalizeTemplate(id, tpl) {
        const def = DEFAULTS[id];
        const t = Object.assign({}, clone(def), tpl || {});
        t.name = def.name;
        t.icon = def.icon;
        t.defaultBackground = def.defaultBackground;
        t.elements = (t.elements || def.elements).map(normalizeElement);
        return t;
    }

    function readLS(key, fallback) {
        try {
            const raw = localStorage.getItem(key);
            return raw ? JSON.parse(raw) : fallback;
        } catch (e) { return fallback; }
    }
    function writeLS(key, value) {
        try { localStorage.setItem(key, JSON.stringify(value)); return true; }
        catch (e) { console.warn('localStorage write failed', e); return false; }
    }

    /* ------------------------------------------------------------------ */
    /* Šablóny                                                             */
    /* ------------------------------------------------------------------ */
    const store = MD.store = {};

    store.defaultTemplate = (id) => normalizeTemplate(id, null);

    store.loadTemplates = function () {
        const saved = readLS(LS_KEY, {}) || {};
        const out = {};
        MD.TEMPLATE_IDS.forEach((id) => { out[id] = normalizeTemplate(id, saved[id]); });
        return out;
    };

    store.loadTemplate = (id) => store.loadTemplates()[id];

    store.saveTemplate = function (id, tpl) {
        const saved = readLS(LS_KEY, {}) || {};
        const { name, icon, defaultBackground, ...rest } = tpl;
        saved[id] = clone(rest);
        return writeLS(LS_KEY, saved);
    };

    store.resetTemplate = function (id) {
        const saved = readLS(LS_KEY, {}) || {};
        delete saved[id];
        writeLS(LS_KEY, saved);
        return store.clearBackground(id);
    };

    /* ------------------------------------------------------------------ */
    /* Pozadia (IndexedDB, fallback localStorage)                          */
    /* ------------------------------------------------------------------ */
    let dbPromise = null;
    function db() {
        if (dbPromise) return dbPromise;
        dbPromise = new Promise((resolve) => {
            if (!('indexedDB' in window)) return resolve(null);
            try {
                const req = indexedDB.open(DB_NAME, 1);
                req.onupgradeneeded = () => req.result.createObjectStore(DB_STORE);
                req.onsuccess = () => resolve(req.result);
                req.onerror = () => resolve(null);
            } catch (e) { resolve(null); }
        });
        return dbPromise;
    }
    function idb(mode, fn) {
        return db().then((d) => new Promise((resolve, reject) => {
            if (!d) return reject(new Error('no-idb'));
            const tx = d.transaction(DB_STORE, mode);
            const req = fn(tx.objectStore(DB_STORE));
            tx.oncomplete = () => resolve(req && req.result);
            tx.onerror = () => reject(tx.error);
        }));
    }
    const blobToDataURL = (blob) => new Promise((res, rej) => {
        const r = new FileReader();
        r.onload = () => res(r.result);
        r.onerror = rej;
        r.readAsDataURL(blob);
    });
    const dataURLToBlob = (url) => fetch(url).then((r) => r.blob());

    store.getBackground = function (id) {
        return idb('readonly', (s) => s.get(id))
            .catch(() => {
                const d = readLS('matchday.bg.' + id, null);
                return d ? dataURLToBlob(d) : null;
            })
            .then((b) => b || null);
    };

    store.setBackground = function (id, blob) {
        return idb('readwrite', (s) => s.put(blob, id)).catch(() =>
            blobToDataURL(blob).then((d) => {
                if (!writeLS('matchday.bg.' + id, d)) throw new Error('Obrázok je príliš veľký pre úložisko prehliadača.');
            })
        );
    };

    store.clearBackground = function (id) {
        try { localStorage.removeItem('matchday.bg.' + id); } catch (e) { /* ignore */ }
        return idb('readwrite', (s) => s.delete(id)).catch(() => null);
    };

    /** URL pozadia pre šablónu (vlastné alebo predvolené) */
    store.backgroundUrl = function (id) {
        return store.getBackground(id).then((blob) =>
            blob ? { url: URL.createObjectURL(blob), custom: true } : { url: MD.assetUrl(DEFAULTS[id].defaultBackground), custom: false }
        );
    };

    /* ------------------------------------------------------------------ */
    /* Vlastné písma (IndexedDB, kľúč "font:<názov>")                      */
    /* ------------------------------------------------------------------ */
    store.listFonts = function () {
        return db().then((d) => new Promise((resolve) => {
            if (!d) return resolve([]);
            const out = [];
            const req = d.transaction(DB_STORE, 'readonly').objectStore(DB_STORE).openCursor();
            req.onsuccess = () => {
                const c = req.result;
                if (!c) return resolve(out);
                if (String(c.key).startsWith('font:')) out.push({ name: String(c.key).slice(5), blob: c.value });
                c.continue();
            };
            req.onerror = () => resolve(out);
        }));
    };

    store.addFont = function (name, blob) {
        return idb('readwrite', (s) => s.put(blob, 'font:' + name)).catch(() => {
            throw new Error('Prehliadač nepodporuje ukladanie písiem (IndexedDB).');
        });
    };

    store.removeFont = (name) => idb('readwrite', (s) => s.delete('font:' + name)).catch(() => null);

    /* ------------------------------------------------------------------ */
    /* Záloha – export / import všetkého do jedného JSON súboru            */
    /* ------------------------------------------------------------------ */
    store.exportAll = async function () {
        const out = { app: 'match-day', version: 1, exportedAt: new Date().toISOString(), templates: readLS(LS_KEY, {}), backgrounds: {}, clientId: MD.clientId ? MD.clientId() : undefined };
        for (const id of MD.TEMPLATE_IDS) {
            const b = await store.getBackground(id);
            if (b) out.backgrounds[id] = await blobToDataURL(b);
        }
        out.fonts = {};
        for (const f of await store.listFonts()) out.fonts[f.name] = await blobToDataURL(f.blob);
        return out;
    };

    store.importAll = async function (json) {
        if (!json || json.app !== 'match-day' || typeof json.templates !== 'object') {
            throw new Error('Súbor nie je platná záloha nastavení Match Day.');
        }
        writeLS(LS_KEY, json.templates);
        if (json.clientId && MD.setClientId) MD.setClientId(json.clientId); // galéria z pôvodného prehliadača
        for (const id of MD.TEMPLATE_IDS) {
            if (json.backgrounds && json.backgrounds[id]) {
                await store.setBackground(id, await dataURLToBlob(json.backgrounds[id]));
            } else {
                await store.clearBackground(id);
            }
        }
        for (const [name, data] of Object.entries(json.fonts || {})) {
            await store.addFont(name, await dataURLToBlob(data));
        }
    };

    store.resetAll = async function () {
        try { localStorage.removeItem(LS_KEY); } catch (e) { /* ignore */ }
        for (const id of MD.TEMPLATE_IDS) await store.clearBackground(id);
    };

    /* ------------------------------------------------------------------ */
    /* Drobné preferencie (téma, posledné hľadania …)                      */
    /* ------------------------------------------------------------------ */
    store.pref = function (key, value) {
        const prefs = readLS(LS_PREFS, {}) || {};
        if (value === undefined) return prefs[key];
        prefs[key] = value;
        writeLS(LS_PREFS, prefs);
        return value;
    };
})(window);

/* =====================================================================
 * Anonymné ID prehliadača – podľa neho server vie, ktoré obrázky
 * patria do galérie tohto prehliadača. Neobsahuje žiadne osobné údaje.
 * ===================================================================== */
(function (window) {
    'use strict';
    const MD = window.MD;
    const KEY = 'matchday.clientId';
    let memId = null;

    function newId() {
        const b = new Uint8Array(16);
        (window.crypto || window.msCrypto).getRandomValues(b);
        return Array.from(b, (x) => x.toString(16).padStart(2, '0')).join('');
    }

    MD.clientId = function () {
        try {
            let id = localStorage.getItem(KEY);
            if (!/^[a-f0-9]{32}$/.test(id || '')) { id = newId(); localStorage.setItem(KEY, id); }
            return id;
        } catch (e) {
            return memId || (memId = newId()); // súkromné okno bez úložiska
        }
    };
    MD.setClientId = function (id) {
        if (!/^[a-f0-9]{32}$/.test(id || '')) return false;
        try { localStorage.setItem(KEY, id); return true; } catch (e) { return false; }
    };
    /** hlavička pre požiadavky na server */
    MD.clientHeaders = (extra) => Object.assign({ 'X-MD-Client': MD.clientId() }, extra || {});
})(window);
