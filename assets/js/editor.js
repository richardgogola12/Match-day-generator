/* =====================================================================
 * Match Day – drag & drop editor šablón
 * ===================================================================== */
(function ($) {
    'use strict';
    const MD = window.MD;

    const canvas = document.getElementById('stageCanvas');
    const stage = document.getElementById('stage');
    const overlay = document.getElementById('overlay');
    const guideV = document.getElementById('guideV');
    const guideH = document.getElementById('guideH');

    const TYPE_ICON = { text: 'bi-fonts', logo: 'bi-shield', goals: 'bi-list-ol', shape: 'bi-square-fill', players: 'bi-people', photo: 'bi-person-bounding-box', table: 'bi-table', fixtures: 'bi-calendar-week' };
    const TYPE_NAME = { text: 'Text', logo: 'Logo', goals: 'Strelci / karty', shape: 'Obdĺžnik', players: 'Hráči', photo: 'Fotka hráča', table: 'Tabuľka', fixtures: 'Zápasy kola' };
    const LISTS = ['goals', 'players', 'table', 'fixtures']; // prvky so spodnou hranicou (maxY)
    const FONTED = ['text', 'goals', 'players', 'table', 'fixtures']; // prvky s písmom

    const S = {
        tplId: null,
        tpl: null,
        boxes: {},
        selected: null,
        bg: null,
        bgCustom: false,
        bgUrl: null,
        sampleLogos: { home: null, away: null, team: null, opponent: null },
        lastLogos: { home: null, away: null, team: null, opponent: null },
        lastTeamLogos: {},
        lastPhoto: null,
        history: [],
        hIndex: -1,
    };

    const params = new URLSearchParams(location.search);
    const lastMatch = (() => { try { return JSON.parse(sessionStorage.getItem('matchday.preview') || 'null'); } catch (e) { return null; } })();

    /* ------------------------------------------------------------------ */
    /* Dáta pre náhľad                                                     */
    /* ------------------------------------------------------------------ */
    const GOAL_NAMES = ['Martin Kováč', 'Ján Novák', 'Peter Horváth', 'Tomáš Szabó-Varga', 'Lukáš Baláž', 'Marek Molnár', 'Dávid Lacko', 'Róbert Takáč', 'Samuel Hudec', 'Adam Krajčovič-Dobrovodský', 'Filip Urban', 'Michal Bartoš'];
    function sampleGoals(n) {
        const out = [];
        for (let i = 0; i < n; i++) {
            out.push({ minute: String(Math.round(4 + (86 / n) * i)), name: GOAL_NAMES[i % GOAL_NAMES.length], side: i % 3 === 1 ? 'away' : 'home', own: i === 3 });
        }
        return out;
    }

    function previewData() {
        const sample = Object.assign({}, MD.SAMPLE.data, {
            goals: sampleGoals(parseInt($('#optGoals').val(), 10)),
            players: MD.samplePlayers(parseInt($('#optPlayers').val(), 10) || 24),
        });
        if ($('#optData').val() === 'last' && lastMatch) {
            // posledný zápas bez hráčov (napr. Match day) – hráči z ukážky
            const hasPlayers = lastMatch.players && ['start', 'subs', 'squad', 'scorers'].some((k) => (lastMatch.players[k] || []).length);
            // chýbajúce časti (tabuľka, kolo, hráč …) doplní ukážka
            const fill = {};
            ['table', 'fixtures', 'player', 'playerMatch', 'milestone', 'cards', 'report', 'roundInfo', 'afterRound'].forEach((k) => {
                const v = lastMatch[k];
                if (v === undefined || v === null || (Array.isArray(v) && !v.length)) fill[k] = sample[k];
            });
            return Object.assign({}, lastMatch, fill, hasPlayers ? {} : { players: sample.players });
        }
        return sample;
    }
    function previewLogos() {
        return $('#optData').val() === 'last' && lastMatch ? S.lastLogos : S.sampleLogos;
    }
    function previewAssets() {
        const last = $('#optData').val() === 'last' && lastMatch;
        return { background: S.bg, logos: previewLogos(), teamLogos: last ? S.lastTeamLogos : {}, photo: last ? S.lastPhoto : null };
    }

    /* ------------------------------------------------------------------ */
    /* Uloženie + história (späť / znova)                                  */
    /* ------------------------------------------------------------------ */
    let saveTimer = null, histTimer = null;
    const $save = $('#saveState');

    function snapshot() { return JSON.stringify({ width: S.tpl.width, height: S.tpl.height, elements: S.tpl.elements }); }

    function pushHistory() {
        const snap = snapshot();
        if (S.history[S.hIndex] === snap) return;
        S.history = S.history.slice(0, S.hIndex + 1);
        S.history.push(snap);
        if (S.history.length > 80) S.history.shift();
        S.hIndex = S.history.length - 1;
        updateUndo();
    }

    function updateUndo() {
        $('#btnUndo').prop('disabled', S.hIndex <= 0);
        $('#btnRedo').prop('disabled', S.hIndex >= S.history.length - 1);
    }

    function restore(i) {
        if (i < 0 || i >= S.history.length) return;
        S.hIndex = i;
        const snap = JSON.parse(S.history[i]);
        Object.assign(S.tpl, snap);
        if (S.selected && !getEl(S.selected)) S.selected = null;
        refreshAll();
        saveNow();
        updateUndo();
    }

    function saveNow() {
        if (MD.store.saveTemplate(S.tplId, S.tpl)) {
            $save.addClass('saved').html('<i class="bi bi-cloud-check"></i> Uložené v prehliadači');
        } else {
            $save.removeClass('saved').html('<i class="bi bi-exclamation-triangle text-danger"></i> Nepodarilo sa uložiť');
        }
    }

    /** Volať po každej zmene */
    function changed(opts) {
        opts = opts || {};
        draw();
        if (opts.layers) renderLayers();
        $save.removeClass('saved').html('<span class="spinner-border spinner-border-sm"></span> Ukladám…');
        clearTimeout(saveTimer);
        saveTimer = setTimeout(saveNow, 250);
        clearTimeout(histTimer);
        histTimer = setTimeout(pushHistory, opts.immediate ? 0 : 450);
    }

    /* ------------------------------------------------------------------ */
    /* Kreslenie + overlay                                                 */
    /* ------------------------------------------------------------------ */
    const getEl = (id) => S.tpl.elements.find((e) => e.id === id);

    let rafPending = false;
    function draw() {
        if (rafPending) return;
        rafPending = true;
        requestAnimationFrame(() => {
            rafPending = false;
            S.boxes = MD.render(canvas, S.tpl, previewData(), previewAssets(), { editor: true });
            renderOverlay();
        });
    }

    function fitStage() {
        const outer = stage.parentElement;
        const maxW = outer.clientWidth - parseFloat(getComputedStyle(outer).paddingLeft) * 2;
        const maxH = Math.max(320, window.innerHeight - 220);
        const ratio = S.tpl.width / S.tpl.height;
        let w = maxW, h = w / ratio;
        if (h > maxH && window.innerWidth >= 1200) { h = maxH; w = h * ratio; }
        stage.style.width = Math.floor(w) + 'px';
        stage.style.height = Math.floor(w / ratio) + 'px';
        const grid = parseInt($('#optSnap').val(), 10);
        stage.style.setProperty('--grid-size', (Math.max(grid, 10) * (w / S.tpl.width)) + 'px');
    }

    const pct = (v, total) => (v / total * 100) + '%';

    function renderOverlay() {
        const W = S.tpl.width, H = S.tpl.height;
        const existing = {};
        overlay.querySelectorAll('.el-box').forEach((n) => { existing[n.dataset.id] = n; });
        overlay.querySelectorAll('.maxy-line').forEach((n) => n.remove());

        S.tpl.elements.forEach((el) => {
            const b = S.boxes[el.id];
            let node = existing[el.id];
            delete existing[el.id];
            if (!b || el.visible === false) { if (node) node.remove(); return; }
            if (!node) {
                node = document.createElement('div');
                node.className = 'el-box';
                node.dataset.id = el.id;
                node.innerHTML = '<span class="el-tag"></span><span class="el-anchor"></span><span class="el-resize" title="Zmeniť veľkosť"></span>';
            }
            overlay.appendChild(node); // poradie = poradie vrstiev
            const pad = 4;
            node.style.left = pct(b.x - pad, W);
            node.style.top = pct(b.y - pad, H);
            node.style.width = pct(b.w + pad * 2, W);
            node.style.height = pct(Math.max(b.h, 10) + pad * 2, H);
            node.querySelector('.el-tag').textContent = el.label || TYPE_NAME[el.type];
            const anchor = node.querySelector('.el-anchor');
            anchor.style.left = pct(el.x - (b.x - pad), b.w + pad * 2);
            anchor.style.top = pct(el.y - (b.y - pad), Math.max(b.h, 10) + pad * 2);
            node.classList.toggle('selected', el.id === S.selected);
            node.style.zIndex = el.id === S.selected ? 3 : '';

            if (LISTS.includes(el.type) && el.maxY && el.id === S.selected) {
                const line = document.createElement('div');
                line.className = 'maxy-line';
                line.style.left = pct(b.x, W);
                line.style.width = pct(b.w, W);
                line.style.top = pct(el.maxY, H);
                line.innerHTML = '<span>max. Y ' + el.maxY + (b.scale && b.scale < 0.999 ? ' · zmenšené na ' + Math.round(b.scale * 100) + ' %' : '') + '</span>';
                overlay.appendChild(line);
            }
        });
        Object.values(existing).forEach((n) => n.remove());
    }

    /* ------------------------------------------------------------------ */
    /* Drag & drop + zmena veľkosti                                        */
    /* ------------------------------------------------------------------ */
    let drag = null;

    function toCanvas(dxPx, dyPx) {
        const r = stage.getBoundingClientRect();
        return [dxPx * S.tpl.width / r.width, dyPx * S.tpl.height / r.height];
    }

    overlay.addEventListener('pointerdown', (e) => {
        const box = e.target.closest('.el-box');
        if (!box) { select(null); return; }
        e.preventDefault();
        const el = getEl(box.dataset.id);
        select(el.id);
        const b = S.boxes[el.id];
        drag = {
            el, mode: e.target.classList.contains('el-resize') ? 'resize' : 'move',
            sx: e.clientX, sy: e.clientY,
            ox: el.x, oy: el.y,
            orig: JSON.parse(JSON.stringify(el)),
            cx: b.x + b.w / 2 - el.x, cy: b.y + b.h / 2 - el.y,
            node: box,
        };
        box.classList.add('dragging');
        overlay.setPointerCapture(e.pointerId);
    });

    overlay.addEventListener('pointermove', (e) => {
        if (!drag) return;
        const [dx, dy] = toCanvas(e.clientX - drag.sx, e.clientY - drag.sy);
        const el = drag.el;
        const snap = parseInt($('#optSnap').val(), 10) || 1;
        const rnd = (v) => Math.round(v / snap) * snap;

        if (drag.mode === 'move') {
            let nx = rnd(drag.ox + dx), ny = rnd(drag.oy + dy);
            // prichytenie na stred plátna
            const W = S.tpl.width, H = S.tpl.height, tol = 8 * S.tpl.width / stage.clientWidth;
            let gv = false, gh = false;
            if (Math.abs(nx + drag.cx - W / 2) < tol) { nx = Math.round(W / 2 - drag.cx); gv = true; }
            if (Math.abs(ny + drag.cy - H / 2) < tol) { ny = Math.round(H / 2 - drag.cy); gh = true; }
            guideV.style.display = gv ? 'block' : 'none';
            guideV.style.left = '50%';
            guideH.style.display = gh ? 'block' : 'none';
            guideH.style.top = '50%';
            // ak sa posúva zoznam strelcov, posúvaj s ním aj spodnú hranicu
            if (LISTS.includes(el.type) && el.maxY) el.maxY = Math.round(drag.orig.maxY + (ny - drag.oy));
            el.x = nx; el.y = ny;
        } else {
            const o = drag.orig;
            if (el.type === 'logo') el.size = Math.max(16, Math.round(o.size + Math.max(dx, dy)));
            else if (el.type === 'shape') { el.w = Math.max(4, rnd(o.w + dx)); el.h = Math.max(4, rnd(o.h + dy)); }
            else if (el.type === 'text') {
                const b = S.boxes[el.id];
                el.size = Math.max(6, Math.round(o.size * (1 + dy / Math.max(20, drag.node.offsetHeight * S.tpl.height / stage.clientHeight))));
                if (b && o.maxWidth) el.maxWidth = Math.max(20, rnd(o.maxWidth + dx * (el.align === 'center' ? 2 : 1)));
            } else if (el.type === 'goals') {
                el.maxWidth = Math.max(40, rnd(o.maxWidth + dx * (el.align === 'center' ? 2 : 1)));
                el.maxY = Math.round(o.maxY + dy);
            } else if (el.type === 'photo') {
                const d = Math.max(dx, dy);
                el.w = Math.max(20, rnd(o.w + (el.shape === 'circle' ? d : dx)));
                el.h = Math.max(20, rnd(o.h + (el.shape === 'circle' ? d : dy)));
            } else if (el.type === 'table' || el.type === 'fixtures') {
                el.w = Math.max(100, rnd(o.w + dx));
                el.maxY = Math.round(o.maxY + dy);
            } else if (el.type === 'players') {
                // šírka stĺpca podľa toho, koľko stĺpcov má zoznam
                el.maxWidth = Math.max(40, rnd(o.maxWidth + dx / Math.max(1, o.columns || 1)));
                el.maxY = Math.round(o.maxY + dy);
            }
        }
        syncProps();
        changed();
    });

    function endDrag() {
        if (!drag) return;
        drag.node.classList.remove('dragging');
        drag = null;
        guideV.style.display = guideH.style.display = 'none';
        changed({ immediate: true });
    }
    overlay.addEventListener('pointerup', endDrag);
    overlay.addEventListener('pointercancel', endDrag);

    /* Klávesnica */
    document.addEventListener('keydown', (e) => {
        const typing = /INPUT|TEXTAREA|SELECT/.test(document.activeElement.tagName);
        if ((e.ctrlKey || e.metaKey) && !typing) {
            if (e.key.toLowerCase() === 'z' && !e.shiftKey) { e.preventDefault(); restore(S.hIndex - 1); return; }
            if (e.key.toLowerCase() === 'y' || (e.key.toLowerCase() === 'z' && e.shiftKey)) { e.preventDefault(); restore(S.hIndex + 1); return; }
        }
        if (typing || !S.selected) return;
        const el = getEl(S.selected);
        const step = e.shiftKey ? 10 : 1;
        const moves = { ArrowLeft: [-step, 0], ArrowRight: [step, 0], ArrowUp: [0, -step], ArrowDown: [0, step] };
        if (moves[e.key]) {
            e.preventDefault();
            el.x += moves[e.key][0];
            el.y += moves[e.key][1];
            if (LISTS.includes(el.type) && el.maxY) el.maxY += moves[e.key][1];
            syncProps();
            changed();
        } else if (e.key === 'Escape') {
            select(null);
        } else if ((e.key === 'Delete' || e.key === 'Backspace') && el.custom) {
            e.preventDefault();
            removeEl(el.id);
        }
    });

    /* ------------------------------------------------------------------ */
    /* Vrstvy                                                              */
    /* ------------------------------------------------------------------ */
    function renderLayers() {
        const $l = $('#layers').empty();
        // najvyššia vrstva hore
        S.tpl.elements.slice().reverse().forEach((el) => {
            const $li = $(
                '<li class="list-group-item">' +
                '<span class="layer-icon"><i class="bi ' + TYPE_ICON[el.type] + '"></i></span>' +
                '<span class="layer-name"></span>' +
                '<button type="button" class="layer-btn" data-act="vis" title="Zobraziť / skryť"><i class="bi"></i></button>' +
                '</li>'
            );
            $li.attr('data-id', el.id)
                .toggleClass('active', el.id === S.selected)
                .toggleClass('is-hidden', el.visible === false);
            $li.find('.layer-name').text(el.label || TYPE_NAME[el.type]);
            $li.find('[data-act="vis"] i').addClass(el.visible === false ? 'bi-eye-slash' : 'bi-eye');
            $l.append($li);
        });
    }

    $('#layers').on('click', '.list-group-item', function (e) {
        const id = this.dataset.id;
        if ($(e.target).closest('[data-act="vis"]').length) {
            const el = getEl(id);
            el.visible = el.visible === false;
            changed({ layers: true, immediate: true });
            return;
        }
        select(id);
    });

    function select(id) {
        S.selected = id;
        $('#layers .list-group-item').each(function () { $(this).toggleClass('active', this.dataset.id === id); });
        renderProps();
        renderOverlay();
    }

    /* ------------------------------------------------------------------ */
    /* Vlastnosti vybraného prvku                                          */
    /* ------------------------------------------------------------------ */
    /** <option> zoznam písiem zoskupený podľa kategórie, každá položka vo vlastnom písme */
    function fontOptionsHtml(current) {
        const groups = MD.fontGroups();
        return Object.keys(groups).map((g) =>
            '<optgroup label="' + esc(g) + '">' + groups[g].map((f) =>
                '<option value="' + esc(f.value) + '" style="font-family:&quot;' + esc(f.value) + '&quot;, sans-serif"' + (f.value === current ? ' selected' : '') + '>' + esc(f.label) + '</option>'
            ).join('') + '</optgroup>'
        ).join('');
    }
    const alignField = { prop: 'align', label: 'Zarovnanie', type: 'align', col: 12 };
    const typo = [
        { prop: 'font', label: 'Písmo', type: 'font', col: 8 },
        { prop: 'size', label: 'Veľkosť', type: 'number', min: 4, max: 600, col: 4 },
        { prop: 'color', label: 'Farba', type: 'color', col: 4 },
        { prop: 'lineHeight', label: 'Riadkovanie', type: 'number', step: 0.05, min: 0.5, max: 4, col: 4 },
        { prop: 'letterSpacing', label: 'Prestrkanie', type: 'number', step: 0.5, min: -20, max: 50, col: 4 },
    ];
    const style = [
        { section: 'Štýl' },
        { prop: 'bold', label: 'Tučné', type: 'switch', col: 4 },
        { prop: 'italic', label: 'Kurzíva', type: 'switch', col: 4 },
        { prop: 'uppercase', label: 'VEĽKÉ', type: 'switch', col: 4 },
        { prop: 'shadow', label: 'Tieň', type: 'switch', col: 4 },
        { prop: 'strokeWidth', label: 'Obrys (px)', type: 'number', min: 0, max: 30, step: 0.5, col: 4 },
        { prop: 'strokeColor', label: 'Farba obrysu', type: 'color', col: 4 },
        { prop: 'opacity', label: 'Krytie', type: 'range', min: 0, max: 1, step: 0.05, col: 12 },
    ];
    const position = [
        { prop: 'x', label: 'X', type: 'number', col: 6 },
        { prop: 'y', label: 'Y', type: 'number', col: 6 },
    ];

    const FIELDS = {
        text: [
            { prop: 'label', label: 'Názov prvku', type: 'text', col: 12 },
            { prop: 'text', label: 'Obsah', type: 'textarea', col: 12, tokens: true },
            ...position,
            { section: 'Písmo a zarovnanie' },
            ...typo,
            alignField,
            { prop: 'maxWidth', label: 'Max. šírka (0 = bez zalomenia)', type: 'number', min: 0, max: 4000, col: 12 },
            { prop: 'showIf', label: 'Zobraziť', type: 'select', options: () => [['', 'vždy'], ['photo', 'iba keď je nahratá fotka hráča'], ['noPhoto', 'iba keď fotka chýba']], col: 12 },
            ...style,
        ],
        logo: [
            { prop: 'label', label: 'Názov prvku', type: 'text', col: 12 },
            { prop: 'team', label: 'Družstvo', type: 'select', options: () => [['home', 'Domáci'], ['away', 'Hostia'], ['team', 'Vybrané družstvo (zostava, súpiska)'], ['opponent', 'Súper vybraného družstva']], col: 12 },
            ...position,
            { prop: 'size', label: 'Veľkosť (px)', type: 'number', min: 16, max: 2000, col: 6 },
            { prop: 'shadow', label: 'Tieň', type: 'switch', col: 6 },
            { prop: 'opacity', label: 'Krytie', type: 'range', min: 0, max: 1, step: 0.05, col: 12 },
        ],
        goals: [
            { prop: 'label', label: 'Názov prvku', type: 'text', col: 12 },
            { prop: 'events', label: 'Zobraziť', type: 'select', options: () => [['goals', 'Góly (strelci)'], ['cards', 'Žlté a červené karty']], col: 6 },
            { prop: 'side', label: 'Tím', type: 'select', options: () => [['home', 'Domáci'], ['away', 'Hostia']], col: 6 },
            ...position,
            { section: 'Osadenie zoznamu' },
            alignField,
            { prop: 'gap', label: 'Rozostup gólov', type: 'number', min: 0, max: 400, col: 6 },
            { prop: 'maxWidth', label: 'Šírka stĺpca', type: 'number', min: 40, max: 4000, col: 6 },
            { prop: 'maxY', label: 'Spodná hranica (Y)', type: 'number', min: 0, max: 8000, col: 6 },
            { prop: 'minScale', label: 'Min. zmenšenie', type: 'select', options: () => [[0.4, '40 %'], [0.5, '50 %'], [0.6, '60 %'], [0.7, '70 %'], [0.8, '80 %'], [1, 'nezmenšovať']], col: 6, number: true },
            { prop: 'autoFit', label: 'Automaticky zmenšiť, keď sa nezmestí', type: 'switch', col: 12 },
            { section: 'Formát riadku' },
            { prop: 'format', label: 'Formát ({min}, {name})', type: 'text', col: 7 },
            { prop: 'ownPrefix', label: 'Vlastný gól', type: 'text', col: 5 },
            { prop: 'ownColor', label: 'Farba vlastného gólu (prázdne = rovnaká)', type: 'colorOpt', col: 12 },
            { section: 'Písmo' },
            ...typo,
            ...style,
        ],
        players: [
            { prop: 'label', label: 'Názov prvku', type: 'text', col: 12 },
            { prop: 'source', label: 'Zoznam', type: 'select', options: () => [['start', 'Základná zostava'], ['subs', 'Náhradníci'], ['squad', 'Súpiska družstva'], ['scorers', 'Najlepší strelci'], ['apps', 'Najviac zápasov'], ['minutes', 'Najviac minút'], ['cards', 'Najviac kariet']], col: 8 },
            { prop: 'limit', label: 'Max. hráčov (0 = všetci)', type: 'number', min: 0, max: 60, col: 4 },
            ...position,
            { section: 'Stĺpce a riadky' },
            { prop: 'columns', label: 'Počet stĺpcov', type: 'select', options: () => [[1, '1'], [2, '2'], [3, '3'], [4, '4']], col: 6, number: true },
            { prop: 'maxWidth', label: 'Šírka stĺpca', type: 'number', min: 40, max: 4000, col: 6 },
            { prop: 'colGap', label: 'Medzera stĺpcov', type: 'number', min: 0, max: 400, col: 6 },
            { prop: 'gap', label: 'Rozostup riadkov', type: 'number', min: 0, max: 400, col: 6 },
            { prop: 'maxY', label: 'Spodná hranica (Y)', type: 'number', min: 0, max: 8000, col: 6 },
            { prop: 'minScale', label: 'Min. zmenšenie', type: 'select', options: () => [[0.4, '40 %'], [0.5, '50 %'], [0.55, '55 %'], [0.6, '60 %'], [0.7, '70 %'], [0.8, '80 %'], [1, 'nezmenšovať']], col: 6, number: true },
            { prop: 'autoFit', label: 'Automaticky zmenšiť, keď sa nezmestí', type: 'switch', col: 12 },
            { section: 'Formát riadku' },
            { prop: 'format', label: 'Formát ({nr}, {name}, {apps}, {goals}, {minutes}, {yellow}, {red})', type: 'text', col: 12 },
            { prop: 'capMark', label: 'Kapitán', type: 'text', col: 6 },
            { prop: 'gkMark', label: 'Brankár', type: 'text', col: 6 },
            { prop: 'numColor', label: 'Farba čísla (prázdne = rovnaká)', type: 'colorOpt', col: 12 },
            { prop: 'emptyText', label: 'Text, keď hráči chýbajú', type: 'text', col: 12 },
            { section: 'Písmo' },
            ...typo,
            ...style,
        ],
        photo: [
            { prop: 'label', label: 'Názov prvku', type: 'text', col: 12 },
            ...position,
            { prop: 'w', label: 'Šírka', type: 'number', min: 10, col: 4 },
            { prop: 'h', label: 'Výška', type: 'number', min: 10, col: 4 },
            { prop: 'shape', label: 'Tvar', type: 'select', options: () => [['circle', 'Kruh'], ['rect', 'Obdĺžnik'], ['none', 'Bez orezania']], col: 4 },
            { prop: 'fit', label: 'Prispôsobenie', type: 'select', options: () => [['cover', 'Vyplniť (orezať)'], ['contain', 'Celá fotka']], col: 6 },
            { prop: 'radius', label: 'Zaoblenie rohov', type: 'number', min: 0, col: 6 },
            { prop: 'borderWidth', label: 'Rámik (px)', type: 'number', min: 0, max: 60, col: 6 },
            { prop: 'borderColor', label: 'Farba rámika', type: 'color', col: 6 },
            { prop: 'bgColor', label: 'Pozadie za fotkou', type: 'color', col: 6 },
            { prop: 'shadow', label: 'Tieň', type: 'switch', col: 6 },
            { section: 'Bez fotky' },
            { prop: 'fallbackText', label: 'Text (napr. {playerNr})', type: 'text', col: 12 },
            { prop: 'fallbackColor', label: 'Farba 1', type: 'color', col: 4 },
            { prop: 'fallbackColor2', label: 'Farba 2', type: 'color', col: 4 },
            { prop: 'color', label: 'Farba textu', type: 'color', col: 4 },
            { prop: 'font', label: 'Písmo', type: 'font', col: 12 },
            { prop: 'opacity', label: 'Krytie', type: 'range', min: 0, max: 1, step: 0.05, col: 12 },
        ],
        table: [
            { prop: 'label', label: 'Názov prvku', type: 'text', col: 12 },
            ...position,
            { prop: 'w', label: 'Šírka', type: 'number', min: 100, col: 4 },
            { prop: 'rowH', label: 'Výška riadku', type: 'number', min: 10, col: 4 },
            { prop: 'maxRows', label: 'Max. riadkov', type: 'number', min: 1, max: 40, col: 4 },
            { prop: 'columns', label: 'Stĺpce (p, w, d, l, score, gd, pts)', type: 'text', col: 12 },
            { prop: 'header', label: 'Hlavička', type: 'switch', col: 4 },
            { prop: 'showLogos', label: 'Logá', type: 'switch', col: 4 },
            { prop: 'ownBold', label: 'Tučne vlastné', type: 'switch', col: 4 },
            { prop: 'maxY', label: 'Spodná hranica (Y)', type: 'number', min: 0, max: 8000, col: 6 },
            { prop: 'autoFit', label: 'Zmenšiť, keď sa nezmestí', type: 'switch', col: 6 },
            { section: 'Farby' },
            { prop: 'color', label: 'Text', type: 'color', col: 4 },
            { prop: 'headColor', label: 'Hlavička', type: 'color', col: 4 },
            { prop: 'ptsColor', label: 'Body', type: 'color', col: 4 },
            { prop: 'highlight', label: 'Zvýraznenie vlastného družstva', type: 'colorOpt', col: 6 },
            { prop: 'ownColor', label: 'Text vlastného', type: 'color', col: 6 },
            { prop: 'stripe', label: 'Pruhovanie riadkov', type: 'colorOpt', col: 12 },
            { section: 'Písmo' },
            { prop: 'font', label: 'Písmo', type: 'font', col: 8 },
            { prop: 'size', label: 'Veľkosť', type: 'number', min: 4, max: 200, col: 4 },
            { prop: 'uppercase', label: 'VEĽKÉ', type: 'switch', col: 6 },
            { prop: 'opacity', label: 'Krytie', type: 'range', min: 0, max: 1, step: 0.05, col: 12 },
        ],
        fixtures: [
            { prop: 'label', label: 'Názov prvku', type: 'text', col: 12 },
            ...position,
            { prop: 'w', label: 'Šírka', type: 'number', min: 100, col: 4 },
            { prop: 'rowH', label: 'Výška riadku', type: 'number', min: 10, col: 4 },
            { prop: 'maxRows', label: 'Max. zápasov', type: 'number', min: 1, max: 40, col: 4 },
            { prop: 'showLogos', label: 'Logá', type: 'switch', col: 4 },
            { prop: 'showDate', label: 'Deň pri čase', type: 'switch', col: 4 },
            { prop: 'uppercase', label: 'VEĽKÉ', type: 'switch', col: 4 },
            { prop: 'maxY', label: 'Spodná hranica (Y)', type: 'number', min: 0, max: 8000, col: 6 },
            { prop: 'autoFit', label: 'Zmenšiť, keď sa nezmestí', type: 'switch', col: 6 },
            { section: 'Farby' },
            { prop: 'color', label: 'Text', type: 'color', col: 4 },
            { prop: 'pillColor', label: 'Výsledok', type: 'color', col: 4 },
            { prop: 'pillText', label: 'Text výsledku', type: 'color', col: 4 },
            { prop: 'pillColor2', label: 'Čas (neodohraný)', type: 'colorOpt', col: 6 },
            { prop: 'highlight', label: 'Zvýraznenie vlastného zápasu', type: 'colorOpt', col: 6 },
            { section: 'Písmo' },
            { prop: 'font', label: 'Písmo', type: 'font', col: 8 },
            { prop: 'size', label: 'Veľkosť', type: 'number', min: 4, max: 200, col: 4 },
            { prop: 'opacity', label: 'Krytie', type: 'range', min: 0, max: 1, step: 0.05, col: 12 },
        ],
        shape: [
            { prop: 'label', label: 'Názov prvku', type: 'text', col: 12 },
            ...position,
            { prop: 'w', label: 'Šírka', type: 'number', min: 1, col: 4 },
            { prop: 'h', label: 'Výška', type: 'number', min: 1, col: 4 },
            { prop: 'radius', label: 'Zaoblenie', type: 'number', min: 0, col: 4 },
            { prop: 'color', label: 'Farba', type: 'color', col: 6 },
            { prop: 'opacity', label: 'Krytie', type: 'range', min: 0, max: 1, step: 0.05, col: 6 },
        ],
    };

    const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

    function fieldHtml(f, el) {
        if (f.section) return '<div class="col-12 props-section mt-3"><h6 class="mb-0">' + f.section + '</h6></div>';
        const id = 'p_' + f.prop;
        const v = el[f.prop];
        let input = '';
        const attrs = (f.min !== undefined ? ' min="' + f.min + '"' : '') + (f.max !== undefined ? ' max="' + f.max + '"' : '') + (f.step !== undefined ? ' step="' + f.step + '"' : '');
        switch (f.type) {
            case 'text':
                input = '<input type="text" class="form-control" id="' + id + '" data-prop="' + f.prop + '" value="' + esc(v) + '">';
                break;
            case 'textarea':
                input = '<textarea class="form-control" rows="2" id="' + id + '" data-prop="' + f.prop + '">' + esc(v) + '</textarea>';
                if (f.tokens) {
                    input += '<div class="token-list mt-2">' + MD.TOKENS.map(([t, d]) => '<span class="badge rounded-pill" data-token="' + t + '" title="' + esc(d) + '">' + t + '</span>').join('') + '</div>';
                }
                break;
            case 'number':
                input = '<input type="number" class="form-control" id="' + id + '" data-prop="' + f.prop + '" data-num="1" value="' + esc(v) + '"' + attrs + '>';
                break;
            case 'range':
                input = '<input type="range" class="form-range" id="' + id + '" data-prop="' + f.prop + '" data-num="1" value="' + esc(v) + '"' + attrs + '>';
                break;
            case 'color':
                input = '<input type="color" class="form-control form-control-color" id="' + id + '" data-prop="' + f.prop + '" value="' + esc(v || '#ffffff') + '">';
                break;
            case 'colorOpt':
                input = '<div class="input-group"><input type="color" class="form-control form-control-color" id="' + id + '" data-prop="' + f.prop + '" value="' + esc(v || '#ffd166') + '"' + (v ? '' : ' data-empty="1" style="opacity:.35"') + '>' +
                    '<button class="btn btn-outline-secondary" type="button" data-clear="' + f.prop + '" title="Rovnaká farba ako ostatné"><i class="bi bi-x-lg"></i></button></div>';
                break;
            case 'select':
                input = '<select class="form-select" id="' + id + '" data-prop="' + f.prop + '"' + (f.number ? ' data-num="1"' : '') + '>' +
                    f.options().map(([val, lab]) => '<option value="' + esc(val) + '"' + (String(val) === String(v) ? ' selected' : '') + '>' + esc(lab) + '</option>').join('') + '</select>';
                break;
            case 'font':
                input = '<select class="form-select font-select" id="' + id + '" data-prop="' + f.prop + '" style="font-family:&quot;' + esc(v) + '&quot;, sans-serif">' + fontOptionsHtml(v) + '</select>';
                break;
            case 'switch':
                return '<div class="col-' + f.col + '"><div class="form-check form-switch mt-1"><input class="form-check-input" type="checkbox" role="switch" id="' + id + '" data-prop="' + f.prop + '" data-bool="1"' + (v ? ' checked' : '') + '><label class="form-check-label small" for="' + id + '">' + f.label + '</label></div></div>';
            case 'align':
                input = '<div class="btn-group w-100" role="group">' + [['left', 'bi-text-left', 'Vľavo'], ['center', 'bi-text-center', 'Na stred'], ['right', 'bi-text-right', 'Vpravo']].map(([a, ic, t]) =>
                    '<input type="radio" class="btn-check" name="p_align" id="p_align_' + a + '" value="' + a + '" data-prop="align"' + (v === a ? ' checked' : '') + '>' +
                    '<label class="btn btn-outline-secondary btn-sm" for="p_align_' + a + '" title="' + t + '"><i class="bi ' + ic + '"></i></label>').join('') + '</div>' +
                    '<div class="form-text">Bod X je ľavý okraj, stred alebo pravý okraj.</div>';
                break;
        }
        return '<div class="col-' + f.col + '"><label class="form-label" for="' + id + '">' + f.label + '</label>' + input + '</div>';
    }

    function renderProps() {
        const $p = $('#props');
        const el = S.selected && getEl(S.selected);
        if (!el) {
            $p.html('<div class="text-center text-muted-2 small py-4 border rounded-3" style="border-style:dashed !important"><i class="bi bi-cursor fs-4 d-block mb-1"></i>Kliknite na prvok v náhľade alebo v zozname vrstiev.</div>');
            return;
        }
        const head =
            '<div class="d-flex align-items-center gap-2 mb-3">' +
            '<span class="badge text-bg-warning"><i class="bi ' + TYPE_ICON[el.type] + ' me-1"></i>' + TYPE_NAME[el.type] + '</span>' +
            '<div class="ms-auto btn-group btn-group-sm">' +
            '<button type="button" class="btn btn-outline-secondary" data-cmd="up" title="Do popredia"><i class="bi bi-layer-forward"></i></button>' +
            '<button type="button" class="btn btn-outline-secondary" data-cmd="down" title="Do pozadia"><i class="bi bi-layer-backward"></i></button>' +
            '<button type="button" class="btn btn-outline-secondary" data-cmd="center" title="Vycentrovať vodorovne"><i class="bi bi-distribute-horizontal"></i></button>' +
            '<button type="button" class="btn btn-outline-secondary" data-cmd="dup" title="Duplikovať"><i class="bi bi-copy"></i></button>' +
            (el.custom ? '<button type="button" class="btn btn-outline-danger" data-cmd="del" title="Zmazať"><i class="bi bi-trash3"></i></button>' : '') +
            '</div></div>';
        $p.html(head + '<div class="row g-2">' + FIELDS[el.type].map((f) => fieldHtml(f, el)).join('') + '</div>');
    }

    /** Aktualizuje hodnoty vo formulári (napr. počas ťahania) bez prekreslenia formulára */
    function syncProps() {
        const el = S.selected && getEl(S.selected);
        if (!el) return;
        $('#props [data-prop]').each(function () {
            if (this === document.activeElement || this.type === 'radio' || this.type === 'checkbox') return;
            const v = el[this.dataset.prop];
            if (v !== undefined && String(this.value) !== String(v)) this.value = v;
        });
    }

    $('#props').on('input change', '[data-prop]', function () {
        const el = getEl(S.selected);
        if (!el) return;
        const p = this.dataset.prop;
        let v;
        if (this.dataset.bool) v = this.checked;
        else if (this.type === 'radio') { if (!this.checked) return; v = this.value; }
        else if (this.dataset.num) { v = parseFloat(this.value); if (isNaN(v)) return; }
        else v = this.value;
        if (this.dataset.empty) { this.removeAttribute('data-empty'); this.style.opacity = ''; }
        el[p] = v;
        if (p === 'font') {
            this.style.fontFamily = '"' + v + '", sans-serif';
            MD.loadFamily(v).then(draw);
        }
        changed({ layers: p === 'label' });
    });

    $('#props').on('click', '[data-clear]', function () {
        const el = getEl(S.selected);
        el[this.dataset.clear] = '';
        renderProps();
        changed();
    });

    $('#props').on('click', '[data-token]', function () {
        const ta = document.getElementById('p_text');
        const t = this.dataset.token;
        const s = ta.selectionStart ?? ta.value.length, e2 = ta.selectionEnd ?? ta.value.length;
        ta.value = ta.value.slice(0, s) + t + ta.value.slice(e2);
        ta.focus();
        ta.selectionStart = ta.selectionEnd = s + t.length;
        $(ta).trigger('input');
    });

    $('#props').on('click', '[data-cmd]', function () {
        const el = getEl(S.selected);
        const list = S.tpl.elements;
        const i = list.indexOf(el);
        switch (this.dataset.cmd) {
            case 'up': if (i < list.length - 1) { list.splice(i, 1); list.splice(i + 1, 0, el); } break;
            case 'down': if (i > 0) { list.splice(i, 1); list.splice(i - 1, 0, el); } break;
            case 'center': {
                const b = S.boxes[el.id];
                if (b) el.x = Math.round(el.x + (S.tpl.width / 2 - (b.x + b.w / 2)));
                syncProps();
                break;
            }
            case 'dup': {
                const copy = JSON.parse(JSON.stringify(el));
                copy.id = uid(el.type);
                copy.custom = true;
                copy.label = (el.label || TYPE_NAME[el.type]) + ' (kópia)';
                copy.x += 20; copy.y += 20;
                if (copy.maxY) copy.maxY += 20;
                list.splice(i + 1, 0, copy);
                S.selected = copy.id;
                renderProps();
                break;
            }
            case 'del': removeEl(el.id); return;
        }
        changed({ layers: true, immediate: true });
    });

    function removeEl(id) {
        S.tpl.elements = S.tpl.elements.filter((e) => e.id !== id);
        S.selected = null;
        renderProps();
        changed({ layers: true, immediate: true });
    }

    const uid = (t) => t + '_' + Date.now().toString(36) + Math.random().toString(36).slice(2, 5);

    $('#addMenu').on('click', '[data-add]', function () {
        const type = this.dataset.add;
        const W = S.tpl.width, H = S.tpl.height;
        const base = { id: uid(type), type, custom: true, visible: true, label: 'Nový ' + TYPE_NAME[type].toLowerCase() };
        const pos = {
            text: { x: Math.round(W / 2), y: Math.round(H / 2), text: 'Nový text {d1name}', size: 40, align: 'center' },
            shape: { x: Math.round(W / 2 - 200), y: Math.round(H / 2 - 40), w: 400, h: 80 },
            logo: { x: Math.round(W / 2 - 60), y: Math.round(H / 2 - 60), team: 'home', size: 120 },
            goals: { x: Math.round(W / 2 - 130), y: Math.round(H / 2), side: 'home', maxY: Math.round(H * 0.9), maxWidth: 260 },
            players: { x: Math.round(W * 0.16), y: Math.round(H * 0.4), source: S.tplId === 'squad' ? 'squad' : S.tplId === 'stats' ? 'scorers' : 'start', maxY: Math.round(H * 0.88), columns: 2, maxWidth: Math.round(W * 0.33) },
            photo: { x: Math.round(W / 2 - 120), y: Math.round(H / 2 - 120), w: 240, h: 240 },
            table: { x: Math.round(W * 0.16), y: Math.round(H * 0.32), w: Math.round(W * 0.68), maxY: Math.round(H * 0.92) },
            fixtures: { x: Math.round(W * 0.14), y: Math.round(H * 0.33), w: Math.round(W * 0.72), maxY: Math.round(H * 0.92) },
        }[type];
        const el = Object.assign({}, MD.TYPE_DEFAULTS[type], base, pos);
        if (type === 'shape') S.tpl.elements.unshift(el); // podklad dať pod ostatné
        else S.tpl.elements.push(el);
        S.selected = el.id;
        renderProps();
        changed({ layers: true, immediate: true });
    });

    /* ------------------------------------------------------------------ */
    /* Pozadie a rozmer                                                    */
    /* ------------------------------------------------------------------ */
    async function loadBackground() {
        if (S.bgUrl && S.bgCustom) URL.revokeObjectURL(S.bgUrl);
        const b = await MD.store.backgroundUrl(S.tplId);
        S.bgUrl = b.url;
        S.bgCustom = b.custom;
        S.bg = await MD.loadImage(b.url);
        $('#bgThumb').css('background-image', 'url("' + b.url + '")');
        $('#bgReset').prop('disabled', !b.custom);
        draw();
    }

    async function setBackgroundFile(file) {
        if (!file || !/^image\//.test(file.type)) { MD.toast('Vyberte obrázok (PNG, JPG, WEBP).', 'danger'); return; }
        try {
            await MD.store.setBackground(S.tplId, file);
            await loadBackground();
            const img = S.bg;
            if (img && (img.naturalWidth / img.naturalHeight).toFixed(2) !== (S.tpl.width / S.tpl.height).toFixed(2)) {
                MD.toast('Pozadie má iný pomer strán – rozmer upravíte v sekcii „Rozmer obrázka“.', 'info');
            } else {
                MD.toast('Pozadie uložené');
            }
        } catch (e) {
            MD.toast(e.message || 'Pozadie sa nepodarilo uložiť.', 'danger');
        }
    }

    $('#bgFile').on('change', function () { setBackgroundFile(this.files[0]); this.value = ''; });
    $('#bgDrop')
        .on('dragover dragenter', (e) => { e.preventDefault(); $('#bgDrop').addClass('drag'); })
        .on('dragleave drop', (e) => { e.preventDefault(); $('#bgDrop').removeClass('drag'); })
        .on('drop', (e) => setBackgroundFile(e.originalEvent.dataTransfer.files[0]));
    $('#bgReset').on('click', async () => { await MD.store.clearBackground(S.tplId); await loadBackground(); MD.toast('Obnovené predvolené pozadie'); });

    function setSize(w, h) {
        w = Math.max(200, Math.min(4000, Math.round(w)));
        h = Math.max(200, Math.min(4000, Math.round(h)));
        S.tpl.width = w; S.tpl.height = h;
        syncSize();
        fitStage();
        changed({ immediate: true });
    }
    function syncSize() {
        $('#tplW').val(S.tpl.width);
        $('#tplH').val(S.tpl.height);
        $('#sizePreset').val(S.tpl.width + 'x' + S.tpl.height);
        if (!$('#sizePreset').val()) $('#sizePreset').val('');
    }
    $('#sizePreset').on('change', function () { if (this.value) { const [w, h] = this.value.split('x').map(Number); setSize(w, h); } });
    $('#tplW, #tplH').on('change', () => setSize(+$('#tplW').val() || S.tpl.width, +$('#tplH').val() || S.tpl.height));
    $('#sizeFromBg').on('click', () => { if (S.bg) setSize(S.bg.naturalWidth, S.bg.naturalHeight); });


    /* ------------------------------------------------------------------ */
    /* Záložka Písma                                                       */
    /* ------------------------------------------------------------------ */
    const SHOWCASE_TEXT = 'MATCH DAY 3:1 · Ľuboš Šťastný';

    function renderFontsTab() {
        $('#fontAll').html(fontOptionsHtml($('#fontAll').val() || 'Biome'));
        $('#fontAll').css('font-family', '"' + ($('#fontAll').val() || 'Biome') + '", sans-serif');

        const groups = MD.fontGroups();
        $('#fontShowcase').html(Object.keys(groups).map((g) =>
            '<div class="font-group-title">' + esc(g) + '</div>' +
            groups[g].map((f) =>
                '<button type="button" class="font-item" data-font="' + esc(f.value) + '">' +
                '<span class="font-sample" style="font-family:&quot;' + esc(f.value) + '&quot;, sans-serif">' + esc(SHOWCASE_TEXT) + '</span>' +
                '<span class="font-name">' + esc(f.label) + '</span></button>'
            ).join('')
        ).join(''));

        $('#customFonts').html(MD.customFonts.length
            ? MD.customFonts.map((n) =>
                '<li class="list-group-item d-flex align-items-center gap-2"><span class="flex-fill text-truncate" style="font-family:&quot;' + esc(n) + '&quot;, sans-serif">' + esc(n) + '</span>' +
                '<button type="button" class="btn btn-sm btn-outline-danger" data-del-font="' + esc(n) + '" title="Odstrániť"><i class="bi bi-trash3"></i></button></li>').join('')
            : '<li class="list-group-item small text-muted-2">Zatiaľ žiadne vlastné písmo.</li>');
    }

    // ukážky sa načítajú, až keď je záložka otvorená (šetrí dáta)
    $('button[data-bs-target="#tabFonts"]').on('shown.bs.tab', () => {
        MD.fontGroups && Object.values(MD.fontGroups()).flat().forEach((f) => MD.loadFamily(f.value));
    });

    $('#fontAll').on('change', function () { this.style.fontFamily = '"' + this.value + '", sans-serif'; MD.loadFamily(this.value); });

    $('#fontShowcase').on('click', '[data-font]', function () {
        $('#fontAll').val(this.dataset.font).trigger('change');
        $('#fontShowcase .font-item').removeClass('active');
        $(this).addClass('active');
        const el = S.selected && getEl(S.selected);
        if (el && FONTED.includes(el.type)) {
            el.font = this.dataset.font;
            MD.loadFamily(el.font).then(draw);
            renderProps();
            changed();
            MD.toast('Písmo „' + el.font + '“ použité na prvok „' + (el.label || '') + '“');
        }
    });

    $('#fontAllApply').on('click', async function () {
        const font = $('#fontAll').val();
        await MD.loadFamily(font);
        const allTpl = $('#fontAllTemplates').is(':checked');
        const apply = (tpl) => tpl.elements.forEach((e) => { if (FONTED.includes(e.type)) e.font = font; });
        apply(S.tpl);
        if (allTpl) {
            const all = MD.store.loadTemplates();
            MD.TEMPLATE_IDS.filter((id) => id !== S.tplId).forEach((id) => { apply(all[id]); MD.store.saveTemplate(id, all[id]); });
        }
        renderProps();
        changed({ immediate: true });
        MD.toast('Písmo „' + font + '“ nastavené ' + (allTpl ? 'vo všetkých šablónach' : 'v celej šablóne'));
    });

    $('#fontFile').on('change', async function () {
        const files = Array.from(this.files);
        this.value = '';
        for (const file of files) {
            if (!/\.(ttf|otf|woff2?)$/i.test(file.name)) { MD.toast('„' + file.name + '“ nie je písmo (.ttf, .otf, .woff, .woff2).', 'danger'); continue; }
            const name = file.name.replace(/\.[^.]+$/, '').replace(/[-_]+/g, ' ').replace(/[^\p{L}\p{N} ]/gu, '').trim().slice(0, 40) || 'Vlastné písmo';
            try {
                await MD.registerCustomFont({ name, blob: file });
                await MD.store.addFont(name, file);
                MD.toast('Písmo „' + name + '“ pridané');
            } catch (e) {
                MD.toast('Písmo „' + file.name + '“ sa nepodarilo načítať. ' + (e.message || ''), 'danger');
            }
        }
        renderFontsTab();
        renderProps();
    });

    $('#customFonts').on('click', '[data-del-font]', async function () {
        const name = this.dataset.delFont;
        await MD.store.removeFont(name);
        MD.customFonts = MD.customFonts.filter((n) => n !== name);
        renderFontsTab();
        renderProps();
        MD.toast('Písmo „' + name + '“ odstránené (prvky s ním použijú náhradné písmo)', 'info');
    });

    /* ------------------------------------------------------------------ */
    /* Záloha                                                              */
    /* ------------------------------------------------------------------ */
    $('#btnExport').on('click', async () => {
        const json = await MD.store.exportAll();
        const blob = new Blob([JSON.stringify(json, null, 2)], { type: 'application/json' });
        const a = document.createElement('a');
        a.href = URL.createObjectURL(blob);
        a.download = 'match-day-nastavenia-' + new Date().toISOString().slice(0, 10) + '.json';
        document.body.appendChild(a); a.click(); a.remove();
        setTimeout(() => URL.revokeObjectURL(a.href), 2000);
    });

    $('#importFile').on('change', async function () {
        const file = this.files[0];
        this.value = '';
        if (!file) return;
        try {
            await MD.store.importAll(JSON.parse(await file.text()));
            MD.toast('Nastavenia boli importované');
            setTimeout(() => location.reload(), 700); // znovu načíta aj importované písma
        } catch (e) {
            MD.toast(e.message || 'Súbor sa nepodarilo načítať.', 'danger');
        }
    });

    const confirmModal = new bootstrap.Modal('#confirmModal');
    let confirmAction = null;
    function confirmDo(text, fn) { $('#confirmText').text(text); confirmAction = fn; confirmModal.show(); }
    $('#confirmOk').on('click', async () => { confirmModal.hide(); if (confirmAction) await confirmAction(); });

    $('#btnResetTpl').on('click', () => confirmDo('Obnoviť šablónu „' + S.tpl.name + '“ na pôvodné rozloženie aj pozadie?', async () => {
        await MD.store.resetTemplate(S.tplId);
        await loadTemplate(S.tplId);
        MD.toast('Šablóna obnovená');
    }));
    $('#btnResetAll').on('click', () => confirmDo('Obnoviť všetky šablóny a zmazať vlastné pozadia?', async () => {
        await MD.store.resetAll();
        await loadTemplate(S.tplId);
        MD.toast('Všetko obnovené');
    }));

    /* ------------------------------------------------------------------ */
    /* Panel nástrojov                                                     */
    /* ------------------------------------------------------------------ */
    $('#btnUndo').on('click', () => restore(S.hIndex - 1));
    $('#btnRedo').on('click', () => restore(S.hIndex + 1));
    $('#optGrid').on('change', function () { stage.classList.toggle('show-grid', this.checked); MD.store.pref('editorGrid', this.checked); });
    $('#optSnap').on('change', function () { fitStage(); MD.store.pref('editorSnap', this.value); });
    $('#optData, #optGoals, #optPlayers').on('change', draw);
    $('#btnTestPng').on('click', () => {
        // čistý náhľad bez pomocných rámikov
        const c = document.createElement('canvas');
        MD.render(c, S.tpl, previewData(), previewAssets(), {});
        c.toBlob((blob) => {
            const a = document.createElement('a');
            a.href = URL.createObjectURL(blob);
            a.download = 'nahlad-' + S.tplId + '.png';
            document.body.appendChild(a); a.click(); a.remove();
        });
    });

    /* ------------------------------------------------------------------ */
    /* Šablóny                                                             */
    /* ------------------------------------------------------------------ */
    function renderTabs() {
        const all = MD.store.loadTemplates();
        const G = MD.TYPE_GROUPS || { all: MD.TEMPLATE_IDS };
        const names = { match: 'Zápas', team: 'Družstvo', player: 'Hráč' };
        $('#tplTabs').html(Object.keys(G).map((g) =>
            '<li class="nav-group-label">' + (names[g] || '') + '</li>' +
            G[g].map((id) => '<li class="nav-item"><button type="button" class="nav-link' + (id === S.tplId ? ' active' : '') + '" data-tpl="' + id + '"><i class="bi ' + all[id].icon + ' me-1"></i>' + all[id].name + '</button></li>').join('')
        ).join(''));
    }
    $('#tplTabs').on('click', '[data-tpl]', function () {
        if (this.dataset.tpl === S.tplId) return;
        saveNow();
        loadTemplate(this.dataset.tpl);
        const url = new URL(location.href);
        url.searchParams.set('t', this.dataset.tpl);
        history.replaceState(null, '', url);
    });

    async function loadTemplate(id) {
        S.tplId = id;
        S.tpl = MD.store.loadTemplate(id);
        S.selected = null;
        S.history = [];
        S.hIndex = -1;
        overlay.innerHTML = '';
        pushHistory();
        renderTabs();
        renderLayers();
        renderProps();
        syncSize();
        fitStage();
        $('#optGoals').prop('hidden', !S.tpl.elements.some((e) => e.type === 'goals'));
        $('#optPlayers').prop('hidden', !S.tpl.elements.some((e) => e.type === 'players' && e.source === 'squad'));
        await loadBackground();
        MD.ensureFonts(S.tpl).then(draw);
    }

    function refreshAll() {
        renderLayers();
        renderProps();
        syncSize();
        fitStage();
        draw();
    }

    /* ------------------------------------------------------------------ */
    /* Štart                                                               */
    /* ------------------------------------------------------------------ */
    (async function init() {
        if (MD.store.pref('editorGrid')) { $('#optGrid').prop('checked', true); stage.classList.add('show-grid'); }
        if (MD.store.pref('editorSnap')) $('#optSnap').val(MD.store.pref('editorSnap'));

        if (lastMatch) {
            $('#optData option[value="last"]').prop('disabled', false).text('Posledný zápas: ' + (lastMatch.d1name || '').slice(0, 18) + '…');
            if (params.get('preview')) $('#optData').val('last');
        }

        const [h, a] = await Promise.all([MD.loadImage(MD.SAMPLE.logos.home), MD.loadImage(MD.SAMPLE.logos.away)]);
        S.sampleLogos = { home: h, away: a, team: h, opponent: a };
        if (lastMatch) {
            const [lh, la, lt, lo] = await Promise.all([MD.loadImage(lastMatch.logoHome), MD.loadImage(lastMatch.logoAway), MD.loadImage(lastMatch.logoTeam), MD.loadImage(lastMatch.logoOpponent)]);
            S.lastLogos = { home: lh, away: la, team: lt || lh, opponent: lo || la };
            // logá v tabuľke / kole a fotka hráča z generátora
            const urls = [...new Set([...(lastMatch.table || []).map((r) => r.logo), ...(lastMatch.fixtures || []).flatMap((m) => [m.homeLogo, m.awayLogo])].filter(Boolean))];
            const imgs = await Promise.all(urls.map((u) => MD.loadImage('api-logo?url=' + encodeURIComponent(u))));
            urls.forEach((u, i) => { if (imgs[i]) S.lastTeamLogos[u] = imgs[i]; });
            if (lastMatch.photoKey && MD.store.getPhoto) {
                const b = await MD.store.getPhoto(lastMatch.photoKey);
                if (b) S.lastPhoto = await MD.loadImage(URL.createObjectURL(b));
            }
        }

        await MD.ensureFonts();
        renderFontsTab();
        const t = params.get('t');
        await loadTemplate(MD.TEMPLATE_IDS.includes(t) ? t : 'matchday');

        let rt;
        window.addEventListener('resize', () => { clearTimeout(rt); rt = setTimeout(fitStage, 80); });
        if (document.fonts) {
            document.fonts.ready.then(draw);
            document.fonts.addEventListener && document.fonts.addEventListener('loadingdone', draw);
        }
    })();
})(jQuery);
