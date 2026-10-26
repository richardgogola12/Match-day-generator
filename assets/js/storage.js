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
    };
    MD.TYPE_DEFAULTS = TYPE_DEFAULTS;

    MD.TEMPLATE_IDS = Object.keys(DEFAULTS);

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

/* =====================================================================
 * Technické údaje o zariadení pre log generovania (storage/logs/*.txt).
 * Zbierajú sa iba štandardné údaje, ktoré prehliadač bežne poskytuje
 * (žiadna poloha, žiadny "fingerprinting" plátna / zvuku / písiem).
 * ===================================================================== */
(function (window) {
    'use strict';
    const MD = window.MD;
    let cache = null;

    MD.clientInfo = async function () {
        if (cache) return cache;
        const n = navigator, s = window.screen || {};
        const mm = (q) => { try { return window.matchMedia(q).matches; } catch (e) { return null; } };
        const info = {
            userAgent: n.userAgent,
            platform: n.platform || '',
            vendor: n.vendor || '',
            language: n.language || '',
            languages: (n.languages || []).join(', '),
            timezone: (Intl.DateTimeFormat().resolvedOptions().timeZone) || '',
            tzOffsetMin: -new Date().getTimezoneOffset(),
            localTime: new Date().toString(),
            screen: (s.width || '?') + '×' + (s.height || '?'),
            screenAvail: (s.availWidth || '?') + '×' + (s.availHeight || '?'),
            colorDepth: s.colorDepth || '',
            pixelRatio: window.devicePixelRatio || 1,
            viewport: window.innerWidth + '×' + window.innerHeight,
            orientation: (s.orientation && s.orientation.type) || '',
            cpuCores: n.hardwareConcurrency || '',
            memoryGB: n.deviceMemory || '',
            touchPoints: n.maxTouchPoints || 0,
            cookies: n.cookieEnabled,
            doNotTrack: n.doNotTrack || window.doNotTrack || '',
            online: n.onLine,
            pwaInstalled: mm('(display-mode: standalone)') || n.standalone === true,
            darkMode: mm('(prefers-color-scheme: dark)'),
            appTheme: document.documentElement.getAttribute('data-bs-theme') || '',
            reducedMotion: mm('(prefers-reduced-motion: reduce)'),
            referrer: document.referrer || '',
            page: location.href,
        };
        const c = n.connection || n.mozConnection || n.webkitConnection;
        if (c) Object.assign(info, { netType: c.effectiveType || '', netDownlinkMbps: c.downlink ?? '', netRttMs: c.rtt ?? '', saveData: !!c.saveData });

        // Client Hints (Chrome, Edge, Opera…) – presná verzia systému, architektúra, model zariadenia
        if (n.userAgentData) {
            info.uaMobile = n.userAgentData.mobile;
            info.uaBrands = (n.userAgentData.brands || []).map((b) => b.brand + ' ' + b.version).join(', ');
            try {
                const h = await n.userAgentData.getHighEntropyValues(['platform', 'platformVersion', 'architecture', 'bitness', 'model', 'fullVersionList', 'formFactors']);
                Object.assign(info, {
                    chPlatform: h.platform || '',
                    chPlatformVersion: h.platformVersion || '',
                    chArchitecture: h.architecture || '',
                    chBitness: h.bitness || '',
                    chModel: h.model || '',
                    chFormFactors: (h.formFactors || []).join(', '),
                    chFullVersions: (h.fullVersionList || []).map((b) => b.brand + ' ' + b.version).join(', '),
                });
            } catch (e) { /* nepodporované */ }
        }
        try {
            if (n.storage && n.storage.estimate) {
                const est = await n.storage.estimate();
                info.storageUsedMB = Math.round((est.usage || 0) / 1048576);
            }
        } catch (e) { /* ignore */ }
        return (cache = info);
    };

    /** údaje zakódované do hlavičky (hlavičky musia byť ASCII) */
    MD.clientInfoHeader = async function () {
        try {
            const json = JSON.stringify(await MD.clientInfo());
            return btoa(unescape(encodeURIComponent(json)));
        } catch (e) { return ''; }
    };
})(window);
