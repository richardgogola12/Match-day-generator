/* Generátor – vykreslí zvolenú šablónu s dátami zápasu, družstva alebo hráča a umožní stiahnutie PNG */
(function ($) {
    'use strict';
    const MD = window.MD;
    const M = window.MD_MATCH || {};
    let type = window.MD_TYPE || 'matchday';
    const MODE = window.MD_MODE || 'match';         // match | team | player
    const SQUAD = () => type === 'squad';

    const canvas = document.getElementById('out');
    const $loading = $('#loading');
    const proxied = (u) => (u ? 'api-logo?url=' + encodeURIComponent(u) : '');
    const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    const qs = new URLSearchParams(location.search);
    const setQs = (k, v) => { const u = new URL(location.href); if (v === '' || v === null || v === undefined) u.searchParams.delete(k); else u.searchParams.set(k, v); history.replaceState(null, '', u); };

    // ktoré družstvo je "naše" (podľa zoznamu zápasov) – predvolená strana zostavy
    let side = ['home', 'away'].includes(qs.get('side')) ? qs.get('side') : (M.myTeam && M.myTeam === M.team2ID ? 'away' : 'home');
    let playerKey = M.player || '';
    let matchRef = M.matchRef || '';
    let roundIdx = null;

    const data = {
        d1name: M.d1name, d2name: M.d2name, startDate: M.startDate,
        competition: M.competition, round: M.round, score1: M.score1, score2: M.score2,
        category: M.category || '', season: M.season || '',
        goals: [], cards: [], report: {},
        players: { start: [], subs: [], squad: [] },
    };

    /* ------------------------------------------------------------------ */
    /* Načítanie dát                                                       */
    /* ------------------------------------------------------------------ */
    let logosPromise = null, matchPromise = null, teamPromise = null;
    let matchInfo = null;  // { goals, cards, subs, report, lineups }
    let teamInfo = null;   // { team, players, coach, stats, competitionData }
    const logoCache = {};

    function logo(url) {
        if (!url) return Promise.resolve(null);
        if (!logoCache[url]) logoCache[url] = MD.loadImage(proxied(url));
        return logoCache[url];
    }

    function loadLogos() {
        if (!logosPromise) logosPromise = Promise.all([logo(M.d1url), logo(M.d2url)]).then(([home, away]) => ({ home, away }));
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

    /** Zápis o stretnutí (strelci, karty, zostavy, súhrn) – načíta sa raz */
    function loadMatch(force) {
        if (!M.matchID) return Promise.resolve(null);
        if (!matchPromise || force) {
            matchPromise = getJson('api-match?id=' + encodeURIComponent(M.matchID) + '&home=' + encodeURIComponent(M.team1ID || '') + '&away=' + encodeURIComponent(M.team2ID || '') + (force ? '&_=' + Date.now() : ''))
                .then((j) => {
                    matchInfo = j;
                    data.goals = j.goals || [];
                    data.cards = j.cards || [];
                    data.report = j.report || {};
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

    /** Družstvo: hráči, štatistiky, tabuľka a kolá */
    function loadTeam(force) {
        if (!M.team) return Promise.resolve(null);
        if (!teamPromise || force) {
            $('#loadingText').text('Načítavam údaje družstva zo zápisov o stretnutí…');
            teamPromise = getJson('api-team?team=' + encodeURIComponent(M.team) + (force ? '&_=' + Date.now() : ''))
                .then((j) => { teamInfo = j; afterTeamLoaded(); return j; })
                .catch((e) => {
                    MD.toast('Údaje družstva sa nepodarilo načítať: ' + e.message, 'danger');
                    teamInfo = { players: [], coach: '', stats: {}, competitionData: null, failed: true };
                    return teamInfo;
                });
        }
        return teamPromise;
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
            cols[s].forEach((g) => $('<li></li>').text(g.minute + "' " + (g.own ? '(vl.) ' : '') + g.name).appendTo($col.find('ul')));
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
    const overrideKey = () => (SQUAD() ? 'squad:' + (M.team || M.d1name) + ':' + (M.season || '') : 'lineup:' + (M.matchID || M.d1name + M.d2name) + ':' + side);

    /** Hráči zo Sportnetu v jednotnom tvare {nr, name, role, captain, gk} */
    function sourcePlayers() {
        if (SQUAD()) {
            const t = teamInfo || {};
            const list = (t.players || []).filter((p) => p.apps > 0);
            return { list: list.map((p) => ({ nr: p.nr || '', name: p.name, role: 'show', captain: !!p.captain, gk: !!p.gk, apps: p.apps, goals: p.goals, starts: p.starts })), coach: t.coach || '' };
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

    /* ------------------------------------------------------------------ */
    /* Dáta družstva a hráča pre šablóny                                   */
    /* ------------------------------------------------------------------ */
    const comp = () => (teamInfo && teamInfo.competitionData) || null;
    const ownTeam = () => (teamInfo && teamInfo.team) || { id: '', name: M.d1name, logo: M.d1url };
    const findPlayer = () => ((teamInfo && teamInfo.players) || []).find((p) => p.key === playerKey) || null;
    const leaders = (f, n) => ((teamInfo && teamInfo.players) || []).filter((p) => p[f] > 0).sort((a, b) => b[f] - a[f] || b.apps - a.apps).slice(0, n || 10);

    function tableRows() {
        const c = comp();
        return c ? c.table.map((r) => ({ pos: r.pos, name: r.name, p: r.p, w: r.w, d: r.d, l: r.l, gf: r.gf, ga: r.ga, pts: r.pts, own: !!r.own, logo: r.logo })) : [];
    }

    function afterRoundTxt() {
        const c = comp();
        if (!c || c.lastRound === null || c.lastRound === undefined) return '';
        const r = c.rounds[c.lastRound];
        return r ? 'po ' + (r.no || r.name) + '. kole' : '';
    }

    function fixturesOf(r) {
        const own = ownTeam().id;
        let list = (r ? r.matches : []).map((m) => {
            const d = m.date ? new Date(m.date) : null;
            const time = d && !isNaN(d) ? d.toLocaleTimeString('sk-SK', { hour: '2-digit', minute: '2-digit', timeZone: 'Europe/Bratislava' }) : '';
            const day = d && !isNaN(d) ? ['Ne', 'Po', 'Ut', 'St', 'Št', 'Pi', 'So'][new Date(d.toLocaleString('en-US', { timeZone: 'Europe/Bratislava' })).getDay()] : '';
            return { home: m.home.name, away: m.away.name, hs: m.home.score, as: m.away.score, played: !!m.played, time, day, own: m.home.id === own || m.away.id === own, homeLogo: m.home.logo, awayLogo: m.away.logo, date: m.date };
        });
        if ($('#roundOwnFirst').is(':checked')) list = list.sort((a, b) => (b.own - a.own) || String(a.date).localeCompare(String(b.date)));
        return list;
    }

    /** Doplní do `data` všetko, čo potrebuje aktuálny typ obrázka */
    function applyTypeData() {
        const T = ownTeam();
        if (MODE !== 'match') {
            data.team = T.name || M.d1name;
            data.coach = (teamInfo && teamInfo.coach) || '';
            data.table = tableRows();
            data.afterRound = afterRoundTxt();
            data.competition = (comp() && comp().competition) || M.competition;
        }

        if (type === 'lineup' || SQUAD()) {
            const eff = effectivePlayers();
            const clean = (p) => ({ nr: p.nr, name: p.name, captain: p.captain, gk: p.gk, apps: p.apps, goals: p.goals, starts: p.starts });
            if (SQUAD()) {
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
            data.coach = eff.coach || data.coach || '';
            return eff;
        }

        if (type === 'stats') {
            data.players = { scorers: leaders('goals'), apps: leaders('apps'), cards: ((teamInfo && teamInfo.players) || []).filter((p) => p.yellow + p.red > 0).sort((a, b) => (b.red * 3 + b.yellow) - (a.red * 3 + a.yellow)).slice(0, 10) };
        }

        if (type === 'round') {
            const c = comp();
            const r = c && c.rounds[roundIdx ?? 0];
            data.fixtures = fixturesOf(r);
            const nPlayed = data.fixtures.filter((m) => m.played).length;
            data.roundInfo = { title: !data.fixtures.length ? 'KOLO' : nPlayed === data.fixtures.length ? 'VÝSLEDKY KOLA' : nPlayed === 0 ? 'PROGRAM KOLA' : 'KOLO', no: r ? r.no : '' };
            data.round = r ? String(r.no || r.name) : '';
            data.startDate = r && r.matches[0] ? r.matches[0].date : '';
        }

        if (MODE === 'player') {
            const P = findPlayer();
            data.player = P || { name: '', nr: '' };
            data.startDate = '';
            if (type === 'motm' && P) {
                const m = P.matches.find((x) => x.id === matchRef) || P.matches[P.matches.length - 1];
                if (m) {
                    data.playerMatch = { home: m.home ? T.name : m.opponent, away: m.home ? m.opponent : T.name, score: m.score, goals: m.goals || 0 };
                    data.opponent = m.opponent;
                    data.round = m.round;
                    data.startDate = m.date;
                } else data.playerMatch = {};
            }
            if (type === 'milestone') {
                const kind = $('#msKind').val() || M.mkind || 'apps';
                const val = parseInt($('#msValue').val(), 10) || (kind === 'goals' ? P && P.goals : P && P.apps) || '';
                const label = kind === 'custom' ? $('#msLabel').val() : (kind === 'goals' ? 'gól za ' : 'zápas za ') + (T.name || M.d1name);
                data.milestone = { value: val, label };
            }
        }
        return null;
    }

    /* ------------------------------------------------------------------ */
    /* Bočné panely                                                        */
    /* ------------------------------------------------------------------ */
    function afterTeamLoaded() {
        const c = comp();
        // kolá
        if (c && $('#selRound').length) {
            const want = parseInt(M.roundNo, 10);
            const def = c.nextRound ?? c.lastRound ?? 0;
            const found = c.rounds.findIndex((r) => r.no === want);
            roundIdx = roundIdx ?? (found >= 0 ? found : def);
            $('#selRound').html(c.rounds.map((r, i) => {
                const p = r.matches.filter((m) => m.played).length;
                return '<option value="' + i + '"' + (i === roundIdx ? ' selected' : '') + '>' + esc(r.name) + (p === r.matches.length ? ' – odohrané' : p ? ' – čiastočne' : '') + '</option>';
            }).join(''));
        }
        // hráči
        if ($('#selPlayer').length) {
            const list = (teamInfo.players || []).filter((p) => p.apps > 0);
            if (!playerKey && list.length) playerKey = list[0].key;
            $('#selPlayer').html(list.length ? list.map((p) => '<option value="' + esc(p.key) + '"' + (p.key === playerKey ? ' selected' : '') + '>' + esc((p.nr ? p.nr + ' · ' : '') + p.name) + '</option>').join('') : '<option value="">Žiadni hráči</option>');
            syncPlayerCard();
        }
        // info o družstve
        const st = teamInfo.stats || {};
        const own = (c && c.table.find((r) => r.own)) || null;
        $('#teamInfo').html(teamInfo.failed ? '<span class="text-danger">Údaje sa nepodarilo načítať.</span>' :
            '<dl class="kv mb-0">' +
            (own ? '<dt>Tabuľka</dt><dd>' + own.pos + '. miesto · ' + own.pts + ' b.</dd>' : '') +
            '<dt>Bilancia</dt><dd>' + (st.w || 0) + ' V · ' + (st.d || 0) + ' R · ' + (st.l || 0) + ' P</dd>' +
            '<dt>Skóre</dt><dd>' + (st.gf || 0) + ':' + (st.ga || 0) + '</dd>' +
            (teamInfo.coach ? '<dt>Tréner</dt><dd>' + esc(teamInfo.coach) + '</dd>' : '') +
            '<dt>Zápisy</dt><dd>' + (teamInfo.matchesUsed || 0) + ' z ' + (teamInfo.played || 0) + ' zápasov</dd></dl>');
    }

    function photoKey() { return MD.store.photoKey(ownTeam().id || M.team, playerKey); }
    let photoUrl = null;
    let photoImg = null;
    async function loadPhoto() {
        if (photoUrl) URL.revokeObjectURL(photoUrl);
        photoUrl = null; photoImg = null;
        if (MODE !== 'player' || !playerKey) return null;
        const b = await MD.store.getPhoto(photoKey());
        if (b) { photoUrl = URL.createObjectURL(b); photoImg = await MD.loadImage(photoUrl); }
        else {
            // fotka zo Sportnetu (ak ju zápis obsahuje) – vlastná nahratá fotka má vždy prednosť
            const P = findPlayer();
            if (P && P.photo) { photoImg = await MD.loadImage('api-logo?raw=1&url=' + encodeURIComponent(P.photo)); if (photoImg) $('#genPhotoImg').attr('src', photoImg.src).prop('hidden', false); }
            $('#genPhotoRemove').prop('hidden', true);
            return photoImg;
        }
        $('#genPhotoImg').attr('src', photoUrl || '').prop('hidden', !photoUrl);
        $('#genPhotoRemove').prop('hidden', !photoUrl);
        return photoImg;
    }

    function syncPlayerCard() {
        const P = findPlayer();
        $('#genPhotoNr').text(P && P.nr ? P.nr : '?');
        $('#heroPlayer').text(P ? P.name + ' · ' : '');
        $('#motmBox').prop('hidden', type !== 'motm');
        $('#milestoneBox').prop('hidden', type !== 'milestone');
        if (!P) { $('#playerStats').text(''); return; }
        $('#playerStats').text(P.apps + ' zápasov · ' + P.goals + ' gólov · ' + P.yellow + ' ŽK / ' + P.red + ' ČK');
        // zápasy pre "Hráč zápasu"
        const ms = P.matches.filter((m) => m.score !== '').reverse();
        if (!ms.find((m) => m.id === matchRef) && ms[0]) matchRef = ms[0].id;
        $('#selMatch').html(ms.map((m) => '<option value="' + esc(m.id) + '"' + (m.id === matchRef ? ' selected' : '') + '>' + esc(m.round + ' · ' + (m.home ? 'doma ' : 'vonku ') + m.opponent + ' ' + m.score + (m.goals ? ' · ' + m.goals + ' g.' : '')) + '</option>').join('') || '<option>Žiadne zápasy</option>');
        // jubileum
        // číslo jubilea: z adresy (napr. 100. zápas za kariéru), inak počet v sezóne – kým ho používateľ neprepíše
        if (!$('#msValue').data('touched')) $('#msValue').val(($('#msKind').val() || 'apps') === 'goals' ? P.goals : P.apps);
        $('#msHint').text(P.apps + ' zápasov, ' + P.goals + ' gólov');
    }

    function renderPlayersCard() {
        const show = type === 'lineup' || SQUAD();
        $('#playersCard').prop('hidden', !show);
        $('#roundCard').prop('hidden', type !== 'round');
        if (MODE === 'player') syncPlayerCard();
        if (!show) return;
        const eff = applyTypeData();
        $('#playersTitle').text(SQUAD() ? 'Súpiska' : 'Zostava');
        $('#sideSwitch [data-side]').removeClass('active').filter('[data-side="' + side + '"]').addClass('active');
        $('#playersSource').html(eff.edited ? '<i class="bi bi-pencil me-1"></i>upravené ručne' : '<i class="bi bi-cloud me-1"></i>Sportnet');

        let status;
        if (SQUAD()) {
            const t = teamInfo || {};
            const n = data.players.squad.length;
            status = !n ? '<div class="alert alert-warning py-2 mb-0"><i class="bi bi-exclamation-triangle me-1"></i>' + (t.failed ? 'Súpisku sa nepodarilo načítať.' : 'V zápisoch o stretnutí sa nenašli žiadni hráči.') + ' Hráčov môžete doplniť ručne.</div>'
                : '<span class="text-muted-2">' + n + ' hráčov' + (t.matchesUsed ? ' · zo zápisov ' + t.matchesUsed + ' ' + (t.matchesUsed === 1 ? 'zápasu' : 'zápasov') : '') + (data.coach ? ' · tréner ' + esc(data.coach) : '') + '</span>';
        } else {
            const a = data.players.start.length, b = data.players.subs.length;
            status = !a && !b ? '<div class="alert alert-warning py-2 mb-0"><i class="bi bi-exclamation-triangle me-1"></i>' + ((matchInfo || {}).failed ? 'Zápis o stretnutí sa nepodarilo načítať.' : 'Zostava zatiaľ nie je v zápise o stretnutí (klub ju zvyčajne zadáva pred zápasom).') + ' Môžete ju doplniť ručne.</div>'
                : '<span class="text-muted-2">' + a + ' v základe · ' + b + ' náhradníkov' + (data.coach ? ' · tréner ' + esc(data.coach) : '') + '</span>'
                  + (!eff.edited && (((matchInfo || {}).lineups || {})[side] || {}).guessed
                      ? '<div class="alert alert-info py-2 mt-2 mb-0"><i class="bi bi-info-circle me-1"></i>Zápis nerozlišuje základ a náhradníkov – prvých 11 hráčov zo zápisu je v základe. Skontrolujte to cez <strong>Upraviť hráčov</strong>.</div>' : '');
        }
        $('#playersStatus').html(status);

        const line = (p) => '<li><span class="nr">' + esc(p.nr || '–') + '</span>' + esc(p.name) + (p.captain ? ' <span class="badge text-bg-warning">C</span>' : '') + (p.gk ? ' <span class="badge text-bg-secondary">B</span>' : '') + '</li>';
        const lists = SQUAD() ? [['', data.players.squad]] : [['Základ', data.players.start], ['Náhradníci', data.players.subs]];
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

        try {
            const tpl = MD.store.loadTemplate(type);
            const needMatch = MODE === 'match' && ['goals', 'lineup', 'report'].includes(type);
            const [, bg, logos] = await Promise.all([
                MD.ensureFonts(tpl),
                MD.store.backgroundUrl(type).then((b) => MD.loadImage(b.url)),
                loadLogos(),
                needMatch ? loadMatch() : Promise.resolve(),
                MODE !== 'match' ? loadTeam() : Promise.resolve(),
            ]);
            if (seq !== renderSeq) return;
            renderPlayersCard();
            applyTypeData();
            const T = ownTeam();

            // logá: družstvo / súper, logá v tabuľke a kole, fotka hráča
            const assets = { background: bg, logos: { home: logos.home, away: logos.away }, teamLogos: {} };
            if (MODE === 'match') {
                assets.logos.team = side === 'away' ? logos.away : logos.home;
                assets.logos.opponent = side === 'away' ? logos.home : logos.away;
            } else {
                assets.logos.team = await logo(T.logo || M.d1url);
                let oppUrl = '';
                if (type === 'motm') {
                    const c = comp();
                    const mm = c && c.matches.find((x) => x.id === matchRef);
                    if (mm) oppUrl = mm.home.id === T.id ? mm.away.logo : mm.home.logo;
                }
                assets.logos.opponent = oppUrl ? await logo(oppUrl) : null;
                const urls = [...new Set([...(type === 'table' || type === 'stats' ? (data.table || []).map((r) => r.logo) : []), ...(type === 'round' ? (data.fixtures || []).flatMap((m) => [m.homeLogo, m.awayLogo]) : [])].filter(Boolean))];
                const imgs = await Promise.all(urls.map(logo));
                urls.forEach((u, i) => { if (imgs[i]) assets.teamLogos[u] = imgs[i]; });
                if (MODE === 'player') assets.photo = await loadPhoto();
            }
            if (seq !== renderSeq) return;
            MD.render(canvas, tpl, data, assets);
            $('#btnDownload, #btnCopy, #btnSaveServer').prop('disabled', false);
            const empty = (type === 'lineup' && !data.players.start.length && !data.players.subs.length) || (SQUAD() && !data.players.squad.length)
                || (MODE === 'player' && !findPlayer()) || (type === 'table' && !(data.table || []).length) || (type === 'round' && !(data.fixtures || []).length);
            if (empty) srvState('idle', 'Uloženie na server', 'Bez údajov sa obrázok automaticky neukladá – doplňte údaje alebo kliknite na Uložiť.');
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
            const away = side === 'away' && MODE === 'match';
            const T = ownTeam();
            sessionStorage.setItem('matchday.preview', JSON.stringify(Object.assign({}, data, {
                logoHome: proxied(M.d1url), logoAway: proxied(M.d2url),
                logoTeam: proxied(MODE === 'match' ? (away ? M.d2url : M.d1url) : (T.logo || M.d1url)), logoOpponent: proxied(away ? M.d1url : M.d2url),
                photoKey: MODE === 'player' ? photoKey() : '',
            })));
        } catch (e) { /* ignore */ }
    }

    const slug = (s) => String(s || '').normalize('NFD').replace(/[̀-ͯ]/g, '').replace(/[^a-z0-9]+/gi, '-').replace(/^-|-$/g, '').toLowerCase();
    function fileName() {
        const prefix = { matchday: 'matchday', lineup: 'zostava', result: 'vysledok', goals: 'strelci', report: 'suhrn', squad: 'supiska', table: 'tabulka', round: 'kolo', stats: 'statistiky', player: 'profil', motm: 'hrac-zapasu', milestone: 'jubileum' }[type] || type;
        if (MODE === 'match') return [prefix, slug(M.d1name), 'vs', slug(M.d2name)].join('_') + (type === 'lineup' ? '_' + slug(data.team) : '') + '.png';
        if (MODE === 'player') return [prefix, slug((findPlayer() || {}).name), slug(M.d1name)].filter(Boolean).join('_') + '.png';
        return [prefix, slug(M.d1name), slug(M.category), type === 'round' ? (data.round || '') + '-kolo' : '', slug(M.season)].filter(Boolean).join('_') + '.png';
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
            const P = findPlayer();
            const isMatch = MODE === 'match';
            const q = new URLSearchParams({
                type: savedType, d1name: M.d1name || '', d2name: isMatch ? (M.d2name || '') : '',
                startDate: isMatch ? (M.startDate || '') : (savedType === 'motm' || savedType === 'round' ? data.startDate || '' : ''),
                competition: data.competition || M.competition || '', round: isMatch || savedType === 'round' || savedType === 'motm' ? String(data.round || M.round || '') : '',
                score1: isMatch ? (M.score1 || '') : '', score2: isMatch ? (M.score2 || '') : '', matchID: isMatch ? (M.matchID || '') : (savedType === 'motm' ? matchRef : ''),
                season: M.season || '', category: M.category || '', teamID: M.team || '', variant: savedType === 'lineup' ? side : '',
                player: MODE === 'player' && P ? P.name : '', playerKey: MODE === 'player' && P ? P.key : '',
                roundNo: savedType === 'round' ? String((data.roundInfo || {}).no || '') : '',
                mvalue: savedType === 'milestone' ? String((data.milestone || {}).value || '') : '',
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
        if (MD.store.pref('autoSave') === false) $('#autoSave').prop('checked', false);
    } catch (e) { /* ignore */ }
    $('#autoSave').on('change', function () {
        MD.store.pref('autoSave', this.checked);
        if (this.checked && !$('#btnSaveServer').prop('disabled')) saveToServer();
    });
    $('#btnSaveServer').on('click', saveToServer);
    $('#srvCopy').on('click', function () {
        navigator.clipboard.writeText($(this).data('url')).then(() => MD.toast('Odkaz skopírovaný'), () => MD.toast('Odkaz sa nepodarilo skopírovať', 'danger'));
    });

    /* ------------------------------------------------------------------ */
    /* Prepínače                                                           */
    /* ------------------------------------------------------------------ */
    $('#templateSwitch').on('click', '[data-type]', function () {
        type = this.dataset.type;
        setQs('type', type);
        render();
    });

    $('#sideSwitch').on('click', '[data-side]', function () {
        if (this.dataset.side === side) return;
        side = this.dataset.side;
        setQs('side', side);
        render();
    });

    $('#selRound').on('change', function () {
        roundIdx = parseInt(this.value, 10) || 0;
        const c = comp();
        setQs('roundNo', c && c.rounds[roundIdx] ? c.rounds[roundIdx].no : '');
        render();
    });
    $('#roundOwnFirst').on('change', render);

    $('#selPlayer').on('change', function () {
        playerKey = this.value;
        setQs('player', playerKey);
        $('#msValue').data('touched', false);
        render();
    });
    $('#selMatch').on('change', function () { matchRef = this.value; setQs('matchRef', matchRef); render(); });
    $('#msValue').on('input', function () { $(this).data('touched', true); clearTimeout(this._t); this._t = setTimeout(render, 250); });
    $('#msKind').on('change', function () {
        $('#msLabel').prop('hidden', this.value !== 'custom');
        if (this.value !== 'custom') { $('#msValue').data('touched', false); syncPlayerCard(); }
        render();
    });
    $('#msLabel').on('input', function () { clearTimeout(this._t); this._t = setTimeout(render, 250); });

    $('#genPhotoFile').on('change', async function () {
        const file = this.files[0];
        this.value = '';
        if (!file || !playerKey) return;
        try {
            await MD.store.setPhoto(photoKey(), await MD.store.shrinkImage(file, 1200));
            MD.toast('Fotka uložená v prehliadači');
            render();
        } catch (e) { MD.toast(e.message || 'Fotku sa nepodarilo uložiť.', 'danger'); }
    });
    $('#genPhotoRemove').on('click', async () => { await MD.store.removePhoto(photoKey()); MD.toast('Fotka odstránená', 'info'); render(); });

    $('#btnReloadPlayers').on('click', async () => {
        if (MODE === 'match') await loadMatch(true); else await loadTeam(true);
        render();
        MD.toast('Údaje zo Sportnetu načítané znova', 'info');
    });

    /* ------------------------------------------------------------------ */
    /* Okno "Upraviť hráčov"                                               */
    /* ------------------------------------------------------------------ */
    const pm = new bootstrap.Modal('#playersModal');
    const roleOptions = () => (SQUAD()
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
        const list = eff.list.length ? eff.list : (SQUAD() ? [] : Array.from({ length: 11 }, () => ({ nr: '', name: '', role: 'start' })));
        $('#pmTitle').text(SQUAD() ? 'Upraviť súpisku' : 'Upraviť zostavu');
        $('#pmSub').text(SQUAD() ? [M.d1name, M.category, M.season].filter(Boolean).join(' · ') : (side === 'away' ? M.d2name : M.d1name));
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
        const $r = $(rowHtml({ nr: '', name: '', role: SQUAD() ? 'show' : 'sub' }));
        $('#pmRows').append($r);
        $r.find('[data-f="name"]').trigger('focus');
    });
    $('#pmRows').on('click', '[data-del]', function () { $(this).closest('tr').remove(); });
    $('#pmRows').on('change', '[data-f="captain"]', function () { if (this.checked) $('#pmRows [data-f="captain"]').not(this).prop('checked', false); });
    $('#pmSave').on('click', () => {
        const all = readOverrides();
        const players = readRows();
        if (SQUAD()) players.sort((a, b) => (parseInt(a.nr, 10) || 999) - (parseInt(b.nr, 10) || 999));
        all[overrideKey()] = { players, coach: $('#pmCoach').val().trim(), at: new Date().toISOString() };
        if (!writeOverrides(all)) MD.toast('Úpravy sa nepodarilo uložiť v prehliadači.', 'danger');
        pm.hide();
        render();
        MD.toast(SQUAD() ? 'Súpiska upravená' : 'Zostava upravená');
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
            navigator.share({ files: [file], title: MODE === 'match' ? M.d1name + ' vs ' + M.d2name : M.d1name }).catch(() => {});
        });
    }

    // strelci pre bočný panel aj pri iných typoch (ak je zápas odohratý)
    if (MODE === 'match' && M.matchID && M.score1 !== '' && !['goals', 'lineup', 'report'].includes(type)) loadMatch();
    if (M.mkind) $('#msKind').val(M.mkind);
    if (M.mvalue) $('#msValue').val(M.mvalue).data('touched', true);

    render();
})(jQuery);
