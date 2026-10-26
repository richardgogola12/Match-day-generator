/* Generátor – vykreslí zvolenú šablónu s dátami zápasu (alebo družstva) a umožní stiahnutie PNG */
(function ($) {
    'use strict';
    const MD = window.MD;
    const M = window.MD_MATCH || {};
    let type = window.MD_TYPE || 'matchday';
    const SQUAD = type === 'squad';

    const canvas = document.getElementById('out');
    const $loading = $('#loading');
    const proxied = (u) => (u ? 'api-logo?url=' + encodeURIComponent(u) : '');
    const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

    // ktoré družstvo je "naše" (podľa zoznamu zápasov) – predvolená strana zostavy
    const urlSide = new URLSearchParams(location.search).get('side');
    let side = ['home', 'away'].includes(urlSide) ? urlSide : (M.myTeam && M.myTeam === M.team2ID ? 'away' : 'home');

    const data = {
        d1name: M.d1name, d2name: M.d2name, startDate: M.startDate,
        competition: M.competition, round: M.round, score1: M.score1, score2: M.score2,
        category: M.category || '', season: M.season || '',
        goals: [],
        players: { start: [], subs: [], squad: [] },
    };

    /* ------------------------------------------------------------------ */
    /* Načítanie dát                                                       */
    /* ------------------------------------------------------------------ */
    let logosPromise = null, matchPromise = null, squadPromise = null;
    let matchInfo = null;  // { goals, lineups }
    let squadInfo = null;  // { players, coach, matches, played }

    function loadLogos() {
        if (!logosPromise) {
            logosPromise = Promise.all([MD.loadImage(proxied(M.d1url)), MD.loadImage(proxied(M.d2url))])
                .then(([home, away]) => ({ home, away }));
        }
        return logosPromise;
    }

    async function getJson(url) {
        const r = await fetch(url);
        const txt = await r.text();
        let j;
        try { j = JSON.parse(txt); } catch (e) {
            const plain = txt.replace(/<[^>]+>/g, ' ').replace(/\s+/g, ' ').trim().slice(0, 220);
            throw new Error('server vrátil chybu (HTTP ' + r.status + '): ' + plain);
        }
        if (j.php_warnings) console.warn('PHP:', j.php_warnings);
        if (j.error) throw new Error(j.error);
        return j;
    }

    /** Zápis o stretnutí (strelci + zostavy) – načíta sa raz */
    function loadMatch(force) {
        if (!M.matchID) return Promise.resolve(null);
        if (!matchPromise || force) {
            matchPromise = getJson('api-match?id=' + encodeURIComponent(M.matchID) + '&home=' + encodeURIComponent(M.team1ID || '') + '&away=' + encodeURIComponent(M.team2ID || '') + (force ? '&_=' + Date.now() : ''))
                .then((j) => {
                    matchInfo = j;
                    data.goals = j.goals || [];
                    renderGoalsInfo();
                    return j;
                })
                .catch((e) => {
                    MD.toast('Zápis o stretnutí sa nepodarilo načítať: ' + e.message, 'danger');
                    matchInfo = { goals: [], lineups: {}, failed: true };
                    return matchInfo;
                });
        }
        return matchPromise;
    }

    /** Súpiska družstva (zo zápisov všetkých odohratých zápasov) */
    function loadSquad() {
        if (!M.team) return Promise.resolve(null);
        if (!squadPromise) {
            $('#loadingText').text('Načítavam súpisku zo zápisov o stretnutí…');
            squadPromise = getJson('api-squad?team=' + encodeURIComponent(M.team))
                .then((j) => (squadInfo = j))
                .catch((e) => {
                    MD.toast('Súpisku sa nepodarilo načítať: ' + e.message, 'danger');
                    return (squadInfo = { players: [], coach: '', failed: true });
                });
        }
        return squadPromise;
    }

    function renderGoalsInfo() {
        if (!$('#goalsCols').length || !data.goals.length) return;
        const cols = { home: [], away: [] };
        data.goals.forEach((g) => cols[g.side] && cols[g.side].push(g));
        const $c = $('#goalsCols').empty();
        [['home', M.d1name], ['away', M.d2name]].forEach(([s, name]) => {
            const $col = $('<div class="col-6"><div class="fw-semibold text-truncate mb-1"></div><ul class="list-unstyled mb-0"></ul></div>');
            $col.find('div').text(name);
            if (!cols[s].length) $col.find('ul').append('<li class="text-muted-2">–</li>');
            cols[s].forEach((g) => {
                $('<li></li>').text(g.minute + "' " + (g.own ? '(vl.) ' : '') + g.name).appendTo($col.find('ul'));
            });
            $c.append($col);
        });
        $('#goalsInfo').prop('hidden', false);
    }

    /* ------------------------------------------------------------------ */
    /* Ručné úpravy hráčov (iba v prehliadači)                             */
    /* ------------------------------------------------------------------ */
    const LS_PLAYERS = 'matchday.players.v1';
    const readOverrides = () => { try { return JSON.parse(localStorage.getItem(LS_PLAYERS) || '{}') || {}; } catch (e) { return {}; } };
    const writeOverrides = (o) => { try { localStorage.setItem(LS_PLAYERS, JSON.stringify(o)); return true; } catch (e) { return false; } };
    const overrideKey = () => (SQUAD ? 'squad:' + (M.team || M.d1name) + ':' + (M.season || '') : 'lineup:' + (M.matchID || M.d1name + M.d2name) + ':' + side);

    /** Hráči zo Sportnetu v jednotnom tvare {nr, name, role, captain, gk} */
    function sourcePlayers() {
        if (SQUAD) {
            const s = squadInfo || {};
            return { list: (s.players || []).map((p) => ({ nr: p.nr || '', name: p.name, role: 'show', captain: !!p.captain, gk: !!p.gk, apps: p.apps, goals: p.goals, starts: p.starts })), coach: s.coach || '' };
        }
        const l = ((matchInfo || {}).lineups || {})[side] || {};
        const map = (role) => (p) => ({ nr: p.nr || '', name: p.name, role, captain: !!p.captain, gk: !!p.gk });
        return { list: [...(l.start || []).map(map('start')), ...(l.subs || []).map(map('sub'))], coach: l.coach || '' };
    }

    function effectivePlayers() {
        const ov = readOverrides()[overrideKey()];
        const src = sourcePlayers();
        return ov ? { list: ov.players || [], coach: ov.coach ?? src.coach, edited: true } : { list: src.list, coach: src.coach, edited: false };
    }

    /** Doplní data.players, tím, súpera a trénera pre aktuálny typ */
    function applyTeamData() {
        const eff = effectivePlayers();
        const clean = (p) => ({ nr: p.nr, name: p.name, captain: p.captain, gk: p.gk, apps: p.apps, goals: p.goals, starts: p.starts });
        if (SQUAD) {
            data.team = M.d1name;
            data.opponent = '';
            data.players = { start: [], subs: [], squad: eff.list.filter((p) => p.role !== 'hide' && p.name).map(clean) };
        } else {
            data.team = side === 'away' ? M.d2name : M.d1name;
            data.opponent = side === 'away' ? M.d1name : M.d2name;
            data.players = {
                start: eff.list.filter((p) => p.role === 'start' && p.name).map(clean),
                subs: eff.list.filter((p) => p.role === 'sub' && p.name).map(clean),
                squad: [],
            };
        }
        data.coach = eff.coach || '';
        return eff;
    }

    function renderPlayersCard() {
        const show = type === 'lineup' || SQUAD;
        $('#playersCard').prop('hidden', !show);
        if (!show) return;
        const eff = applyTeamData();
        $('#playersTitle').text(SQUAD ? 'Súpiska' : 'Zostava');
        $('#sideSwitch [data-side]').removeClass('active').filter('[data-side="' + side + '"]').addClass('active');
        $('#playersSource').html(eff.edited ? '<i class="bi bi-pencil me-1"></i>upravené ručne' : '<i class="bi bi-cloud me-1"></i>Sportnet');

        let status = '';
        if (SQUAD) {
            const s = squadInfo || {};
            const n = data.players.squad.length;
            if (!n) status = '<div class="alert alert-warning py-2 mb-0"><i class="bi bi-exclamation-triangle me-1"></i>' + (s.failed ? 'Súpisku sa nepodarilo načítať.' : 'V zápisoch o stretnutí sa nenašli žiadni hráči.') + ' Hráčov môžete doplniť ručne.</div>';
            else status = '<span class="text-muted-2">' + n + ' hráčov' + (s.matches ? ' · zo zápisov ' + s.matches + ' ' + (s.matches === 1 ? 'zápasu' : 'zápasov') : '') + (data.coach ? ' · tréner ' + esc(data.coach) : '') + '</span>';
        } else {
            const a = data.players.start.length, b = data.players.subs.length;
            if (!a && !b) status = '<div class="alert alert-warning py-2 mb-0"><i class="bi bi-exclamation-triangle me-1"></i>' + ((matchInfo || {}).failed ? 'Zápis o stretnutí sa nepodarilo načítať.' : 'Zostava zatiaľ nie je v zápise o stretnutí (klub ju zvyčajne zadáva pred zápasom).') + ' Môžete ju doplniť ručne.</div>';
            else status = '<span class="text-muted-2">' + a + ' v základe · ' + b + ' náhradníkov' + (data.coach ? ' · tréner ' + esc(data.coach) : '') + '</span>';
        }
        $('#playersStatus').html(status);

        const line = (p) => '<li><span class="nr">' + esc(p.nr || '–') + '</span>' + esc(p.name) + (p.captain ? ' <span class="badge text-bg-warning">C</span>' : '') + (p.gk ? ' <span class="badge text-bg-secondary">B</span>' : '') + '</li>';
        const lists = SQUAD
            ? [['', data.players.squad]]
            : [['Základ', data.players.start], ['Náhradníci', data.players.subs]];
        $('#playersPreview').html(lists.filter(([, l]) => l.length).map(([t, l]) =>
            (t ? '<div class="small fw-semibold text-muted-2 mt-2 mb-1">' + t + '</div>' : '') + '<ol class="players-mini">' + l.map(line).join('') + '</ol>'
        ).join(''));
    }

    /* ------------------------------------------------------------------ */
    /* Vykreslenie                                                         */
    /* ------------------------------------------------------------------ */
    let renderSeq = 0;
    async function render() {
        const seq = ++renderSeq;
        $loading.show();
        $('#btnDownload, #btnCopy, #btnSaveServer').prop('disabled', true);
        $('#templateSwitch [data-type]').removeClass('active').filter('[data-type="' + type + '"]').addClass('active');
        $('#btnEdit').attr('href', 'nastavenia?t=' + type + '&preview=1');
        $('#goalsInfo').prop('hidden', !data.goals.length);

        try {
            const tpl = MD.store.loadTemplate(type);
            const needMatch = type === 'goals' || type === 'lineup';
            const [, bg, logos] = await Promise.all([
                MD.ensureFonts(tpl),
                MD.store.backgroundUrl(type).then((b) => MD.loadImage(b.url)),
                loadLogos(),
                needMatch ? loadMatch() : Promise.resolve(),
                SQUAD ? loadSquad() : Promise.resolve(),
            ]);
            if (seq !== renderSeq) return;
            renderPlayersCard();
            applyTeamData();
            const teamLogo = side === 'away' && !SQUAD ? logos.away : logos.home;
            const oppLogo = side === 'away' && !SQUAD ? logos.home : logos.away;
            MD.render(canvas, tpl, data, { background: bg, logos: { home: logos.home, away: logos.away, team: teamLogo, opponent: oppLogo } });
            $('#btnDownload, #btnCopy, #btnSaveServer').prop('disabled', false);
            const noPlayers = (type === 'lineup' && !data.players.start.length && !data.players.subs.length) || (SQUAD && !data.players.squad.length);
            if (noPlayers) srvState('idle', 'Uloženie na server', 'Bez hráčov sa obrázok automaticky neukladá – doplňte hráčov alebo kliknite na Uložiť.');
            else if ($('#autoSave').is(':checked')) saveToServer();
            else srvState('idle', 'Uloženie na server', 'Automatické ukladanie je vypnuté – kliknite na Uložiť.');
        } catch (e) {
            console.error(e);
            MD.toast('Obrázok sa nepodarilo vygenerovať.', 'danger');
        }
        $loading.hide();
        $('#loadingText').text('Pripravujem obrázok…');

        // údaje pre náhľad v editore
        try {
            const away = side === 'away' && !SQUAD;
            sessionStorage.setItem('matchday.preview', JSON.stringify(Object.assign({}, data, {
                logoHome: proxied(M.d1url), logoAway: proxied(M.d2url),
                logoTeam: proxied(away ? M.d2url : M.d1url), logoOpponent: proxied(away ? M.d1url : M.d2url),
            })));
        } catch (e) { /* ignore */ }
    }

    function fileName() {
        const slug = (s) => String(s || '').normalize('NFD').replace(/[\u0300-\u036f]/g, '').replace(/[^a-z0-9]+/gi, '-').replace(/^-|-$/g, '').toLowerCase();
        if (SQUAD) return ['supiska', slug(M.d1name), slug(M.category), slug(M.season)].filter(Boolean).join('_') + '.png';
        const prefix = { matchday: 'matchday', lineup: 'zostava', result: 'vysledok', goals: 'strelci' }[type] || type;
        return [prefix, slug(M.d1name), 'vs', slug(M.d2name)].join('_') + (type === 'lineup' ? '_' + slug(data.team) : '') + '.png';
    }

    const toBlob = () => new Promise((res) => canvas.toBlob(res, 'image/png'));

    /* ------------------------------------------------------------------ */
    /* Uloženie na server                                                  */
    /* ------------------------------------------------------------------ */
    let saveSeq = 0;
    function srvState(state, title, text, url) {
        const icons = { idle: 'bi-hdd-network', busy: 'spinner', ok: 'bi-cloud-check-fill', err: 'bi-exclamation-triangle-fill' };
        $('#srvIcon').attr('class', 'server-save-icon is-' + state)
            .html(state === 'busy' ? '<span class="spinner-border spinner-border-sm"></span>' : '<i class="bi ' + icons[state] + '"></i>');
        $('#srvTitle').text(title);
        $('#srvText').text(text).attr('title', text);
        $('#srvActions').prop('hidden', !url);
        if (url) { $('#srvOpen').attr('href', url); $('#srvCopy').data('url', new URL(url, location.href).href); }
    }

    async function saveToServer() {
        const seq = ++saveSeq;
        const savedType = type;
        srvState('busy', 'Ukladám na server…', 'Obrázok „' + savedType + '“ sa nahráva.');
        try {
            const blob = await toBlob();
            const q = new URLSearchParams({
                type: savedType, d1name: M.d1name || '', d2name: SQUAD ? '' : (M.d2name || ''), startDate: SQUAD ? '' : (M.startDate || ''),
                competition: M.competition || '', round: SQUAD ? '' : (M.round || ''), score1: M.score1 || '', score2: M.score2 || '', matchID: SQUAD ? '' : (M.matchID || ''),
                season: M.season || '', category: M.category || '', teamID: M.team || '', variant: savedType === 'lineup' ? side : '',
            });
            const r = await fetch('api-save?' + q.toString(), { method: 'POST', headers: MD.clientHeaders({ 'Content-Type': 'image/png', 'X-MD-Mode': $('#autoSave').is(':checked') ? 'auto' : 'manual' }), body: blob });
            const txt = await r.text();
            let j;
            try { j = JSON.parse(txt); } catch (e) { throw new Error('server vrátil chybu (HTTP ' + r.status + ')'); }
            if (!r.ok || !j.ok) throw new Error(j.error || ('HTTP ' + r.status));
            if (seq !== saveSeq) return; // medzitým sa prepol typ
            const kb = Math.round(j.bytes / 1024);
            srvState('ok', j.replaced ? 'Aktualizované na serveri' : 'Uložené na serveri', j.file + ' · ' + (kb > 1024 ? (kb / 1024).toFixed(1) + ' MB' : kb + ' kB'), j.url);
        } catch (e) {
            if (seq !== saveSeq) return;
            srvState('err', 'Nepodarilo sa uložiť', e.message);
        }
    }

    try {
        const pref = MD.store.pref('autoSave');
        if (pref === false) $('#autoSave').prop('checked', false);
    } catch (e) { /* ignore */ }
    $('#autoSave').on('change', function () {
        MD.store.pref('autoSave', this.checked);
        if (this.checked && !$('#btnSaveServer').prop('disabled')) saveToServer();
    });
    $('#btnSaveServer').on('click', saveToServer);
    $('#srvCopy').on('click', function () {
        navigator.clipboard.writeText($(this).data('url')).then(() => MD.toast('Odkaz skopírovaný'), () => MD.toast('Odkaz sa nepodarilo skopírovať', 'danger'));
    });

    $('#templateSwitch').on('click', '[data-type]', function () {
        type = this.dataset.type;
        const url = new URL(location.href);
        url.searchParams.set('type', type);
        history.replaceState(null, '', url);
        render();
    });

    $('#sideSwitch').on('click', '[data-side]', function () {
        if (this.dataset.side === side) return;
        side = this.dataset.side;
        const url = new URL(location.href);
        url.searchParams.set('side', side);
        history.replaceState(null, '', url);
        render();
    });

    $('#btnReloadPlayers').on('click', async () => {
        if (SQUAD) { squadPromise = null; squadInfo = null; } else { await loadMatch(true); }
        render();
        MD.toast('Údaje zo Sportnetu načítané znova', 'info');
    });

    /* ------------------------------------------------------------------ */
    /* Okno "Upraviť hráčov"                                               */
    /* ------------------------------------------------------------------ */
    const pm = new bootstrap.Modal('#playersModal');
    const roleOptions = () => (SQUAD
        ? [['show', 'Zobraziť'], ['hide', 'Nezobraziť']]
        : [['start', 'Základ'], ['sub', 'Náhradník'], ['hide', 'Nezobraziť']]);

    function rowHtml(p) {
        return '<tr>' +
            '<td><input type="text" class="form-control form-control-sm text-center" data-f="nr" maxlength="3" inputmode="numeric" value="' + esc(p.nr) + '" aria-label="Číslo"></td>' +
            '<td><input type="text" class="form-control form-control-sm" data-f="name" value="' + esc(p.name) + '" aria-label="Meno" placeholder="Meno a priezvisko"></td>' +
            '<td><select class="form-select form-select-sm" data-f="role" aria-label="Zaradenie">' + roleOptions().map(([v, l]) => '<option value="' + v + '"' + (p.role === v ? ' selected' : '') + '>' + l + '</option>').join('') + '</select></td>' +
            '<td class="text-center"><input type="checkbox" class="form-check-input" data-f="captain" aria-label="Kapitán"' + (p.captain ? ' checked' : '') + '></td>' +
            '<td class="text-center"><input type="checkbox" class="form-check-input" data-f="gk" aria-label="Brankár"' + (p.gk ? ' checked' : '') + '></td>' +
            '<td class="text-end"><button type="button" class="btn btn-sm btn-link text-danger p-0" data-del title="Odstrániť"><i class="bi bi-x-lg"></i></button></td>' +
            '</tr>';
    }

    function openEditor() {
        const eff = effectivePlayers();
        const list = eff.list.length ? eff.list : (SQUAD ? [] : Array.from({ length: 11 }, () => ({ nr: '', name: '', role: 'start' })));
        $('#pmTitle').text(SQUAD ? 'Upraviť súpisku' : 'Upraviť zostavu');
        $('#pmSub').text(SQUAD ? [M.d1name, M.category, M.season].filter(Boolean).join(' · ') : (side === 'away' ? M.d2name : M.d1name));
        $('#pmRows').html(list.map(rowHtml).join(''));
        $('#pmCoach').val(eff.coach || '');
        $('#pmReset').prop('disabled', !eff.edited);
        pm.show();
    }

    function readRows() {
        const out = [];
        $('#pmRows tr').each(function () {
            const g = (f) => $(this).find('[data-f="' + f + '"]');
            const name = g('name').val().trim();
            if (!name) return;
            out.push({ nr: g('nr').val().trim(), name, role: g('role').val(), captain: g('captain').is(':checked'), gk: g('gk').is(':checked') });
        });
        return out;
    }

    $('#btnEditPlayers').on('click', openEditor);
    $('#pmAdd').on('click', () => {
        const $r = $(rowHtml({ nr: '', name: '', role: SQUAD ? 'show' : 'sub' }));
        $('#pmRows').append($r);
        $r.find('[data-f="name"]').trigger('focus');
    });
    $('#pmRows').on('click', '[data-del]', function () { $(this).closest('tr').remove(); });
    // iba jeden kapitán
    $('#pmRows').on('change', '[data-f="captain"]', function () { if (this.checked) $('#pmRows [data-f="captain"]').not(this).prop('checked', false); });
    $('#pmSave').on('click', () => {
        const all = readOverrides();
        let players = readRows();
        if (SQUAD) players.sort((a, b) => (parseInt(a.nr, 10) || 999) - (parseInt(b.nr, 10) || 999));
        all[overrideKey()] = { players, coach: $('#pmCoach').val().trim(), at: new Date().toISOString() };
        if (!writeOverrides(all)) MD.toast('Úpravy sa nepodarilo uložiť v prehliadači.', 'danger');
        pm.hide();
        render();
        MD.toast(SQUAD ? 'Súpiska upravená' : 'Zostava upravená');
    });
    $('#pmReset').on('click', () => {
        const all = readOverrides();
        delete all[overrideKey()];
        writeOverrides(all);
        pm.hide();
        render();
        MD.toast('Použité údaje zo Sportnetu', 'info');
    });

    /* ------------------------------------------------------------------ */
    /* Stiahnutie, kopírovanie, zdieľanie                                  */
    /* ------------------------------------------------------------------ */
    $('#btnDownload').on('click', async () => {
        const blob = await toBlob();
        const a = document.createElement('a');
        a.href = URL.createObjectURL(blob);
        a.download = fileName();
        document.body.appendChild(a);
        a.click();
        a.remove();
        setTimeout(() => URL.revokeObjectURL(a.href), 2000);
    });

    $('#btnCopy').on('click', async () => {
        try {
            await navigator.clipboard.write([new ClipboardItem({ 'image/png': toBlob() })]);
            MD.toast('Obrázok skopírovaný do schránky');
        } catch (e) {
            MD.toast('Prehliadač nepodporuje kopírovanie obrázka.', 'danger');
        }
    });

    if (navigator.canShare && navigator.canShare({ files: [new File([''], 'x.png', { type: 'image/png' })] })) {
        $('#btnShare').prop('hidden', false).on('click', async () => {
            const file = new File([await toBlob()], fileName(), { type: 'image/png' });
            navigator.share({ files: [file], title: SQUAD ? 'Súpiska ' + M.d1name : M.d1name + ' vs ' + M.d2name }).catch(() => {});
        });
    }

    // strelci pre bočný panel aj pri iných typoch (ak je zápas odohratý)
    if (!SQUAD && M.matchID && M.score1 !== '' && type !== 'goals' && type !== 'lineup') loadMatch();

    render();
})(jQuery);
