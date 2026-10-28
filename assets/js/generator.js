/* Generátor – vykreslí zvolenú šablónu s dátami zápasu, družstva alebo hráča a umožní stiahnutie PNG */
(function ($) {
    'use strict';
    const MD = window.MD;
    const M = window.MD_MATCH || {};
    let type = window.MD_TYPE || 'matchday';
    const MODE = window.MD_MODE || 'match';         // match | team | player | club
    const SQUAD = () => type === 'squad';

    const canvas = document.getElementById('out');
    const sq = document.createElement('canvas');      // vykreslená šablóna (štvorec) – výsledok sa skladá do #out
    let brandPromise = null;
    const loadBrand = () => (brandPromise || (brandPromise = MD.store.loadBrand().catch(() => ({ cfg: MD.store.brand() }))));
    const $loading = $('#loading');
    const proxied = (u) => (u ? 'api-logo?url=' + encodeURIComponent(u) : '');
    const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    const qs = new URLSearchParams(location.search);
    const setQs = (k, v) => { const u = new URL(location.href); if (v === '' || v === null || v === undefined) u.searchParams.delete(k); else u.searchParams.set(k, v); history.replaceState(null, '', u); };
    let format = qs.get('format') === 'story' ? 'story' : (MD.store.pref('format') === 'story' ? 'story' : 'square');

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

    /* ---------------- Víkend klubu: všetky zápasy všetkých družstiev ---------------- */
    let clubPromise = null, clubInfo = null, weekStart = null;
    const TZ = 'Europe/Bratislava';
    const localParts = (d) => { const p = {}; new Intl.DateTimeFormat('en-GB', { timeZone: TZ, year: 'numeric', month: '2-digit', day: '2-digit', weekday: 'short', hour: '2-digit', minute: '2-digit', hour12: false }).formatToParts(d).forEach((x) => { p[x.type] = x.value; }); return p; };
    /** "YYYY-MM-DD" pondelka týždňa, v ktorom je dátum (miestny čas) */
    const mondayOf = (d) => {
        const p = localParts(d);
        const wd = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'].indexOf(p.weekday);
        const u = new Date(Date.UTC(+p.year, +p.month - 1, +p.day - wd));
        return u.toISOString().slice(0, 10);
    };
    const addDays = (ymd, n) => { const u = new Date(ymd + 'T00:00:00Z'); u.setUTCDate(u.getUTCDate() + n); return u.toISOString().slice(0, 10); };
    const ymdOf = (d) => { const p = localParts(d); return p.year + '-' + p.month + '-' + p.day; };
    const weekMatches = (ws) => ((clubInfo || {}).matches || []).filter((m) => { const y = ymdOf(new Date(m.date)); return y >= ws && y <= addDays(ws, 6); });
    function loadClub() {
        if (MODE !== 'club' || !M.club) return Promise.resolve(null);
        if (!clubPromise) {
            $('#loadingText').text('Načítavam zápasy všetkých družstiev klubu…');
            clubPromise = getJson('api-club?club=' + encodeURIComponent(M.club) + '&s=' + encodeURIComponent(M.season || ''))
                .then((j) => {
                    clubInfo = j;
                    if (!weekStart) {
                        weekStart = /^\d{4}-\d{2}-\d{2}$/.test(M.week || '') ? mondayOf(new Date(M.week + 'T12:00:00Z')) : mondayOf(new Date());
                        // bez zápasov v tomto týždni -> najbližší týždeň so zápasmi (dopredu, potom dozadu)
                        if (!M.week && !weekMatches(weekStart).length) {
                            const next = (j.matches || []).find((m) => ymdOf(new Date(m.date)) >= weekStart);
                            const prev = [...(j.matches || [])].reverse().find((m) => ymdOf(new Date(m.date)) < weekStart);
                            const pick = next || prev;
                            if (pick) weekStart = mondayOf(new Date(pick.date));
                        }
                    }
                    return j;
                })
                .catch((e) => { MD.toast('Zápasy klubu sa nepodarilo načítať: ' + e.message, 'danger'); clubInfo = { matches: [], failed: true }; weekStart = weekStart || mondayOf(new Date()); return clubInfo; });
        }
        return clubPromise;
    }
    const fmtDay = (ymd, withYear) => { const [y, m, d] = ymd.split('-'); return (+d) + '. ' + (+m) + '.' + (withYear ? ' ' + y : ''); };
    function weekendData() {
        const list = weekMatches(weekStart);
        const days = ['Ne', 'Po', 'Ut', 'St', 'Št', 'Pi', 'So'];
        let rows = list.map((m) => {
            const d = new Date(m.date);
            const p = localParts(d);
            const wd = days[['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'].indexOf(p.weekday)] || '';
            return { home: m.home.name, away: m.away.name, hs: m.home.score, as: m.away.score, played: !!m.played, time: (p.hour === '24' ? '00' : p.hour) + ':' + p.minute, day: wd,
                own: false, homeLogo: m.home.logo, awayLogo: m.away.logo, date: m.date, label: m.cat, result: m.result, cat: m.cat };
        });
        if ($('#weekByCat').is(':checked')) {
            const order = ((clubInfo || {}).teams || []).map((t) => t.cat);
            rows = rows.sort((a, b) => (order.indexOf(a.cat) - order.indexOf(b.cat)) || String(a.date).localeCompare(String(b.date)));
        }
        const nPlayed = rows.filter((r) => r.played).length;
        const ys = list.map((m) => ymdOf(new Date(m.date)));
        const from = ys.length ? ys[0] : weekStart, to = ys.length ? ys[ys.length - 1] : addDays(weekStart, 6);
        const range = from === to ? fmtDay(from, true) : fmtDay(from, from.slice(0, 4) !== to.slice(0, 4)) + ' – ' + fmtDay(to, true);
        return { rows, title: !rows.length ? 'VÍKEND KLUBU' : nPlayed === rows.length ? 'VÝSLEDKY VÍKENDU' : nPlayed === 0 ? 'PROGRAM VÍKENDU' : 'VÍKEND KLUBU', range };
    }
    function renderWeekCard() {
        if (MODE !== 'club') return;
        $('#weekLabel').text(weekStart ? fmtDay(weekStart, false) + ' – ' + fmtDay(addDays(weekStart, 6), true) : '…');
        const list = weekStart ? weekMatches(weekStart) : [];
        const res = { V: 'success', R: 'warning', P: 'danger' };
        $('#weekInfo').html(!clubInfo ? '<span class="text-muted-2">Načítavam…</span>' : !list.length ? '<div class="alert alert-info py-2 mb-0">V tomto týždni klub nehrá. Prepnite týždeň šípkami.</div>'
            : list.map((m) => '<div class="d-flex justify-content-between gap-2 py-1 border-top"><span class="text-truncate"><span class="text-muted-2">' + esc(m.cat) + ':</span> ' + esc(m.home.name) + ' – ' + esc(m.away.name) + '</span><span class="text-nowrap">'
                + (m.played ? '<strong>' + m.home.score + ':' + m.away.score + '</strong> <span class="badge text-bg-' + (res[m.result] || 'secondary') + '">' + esc(m.result) + '</span>' : esc(new Date(m.date).toLocaleString('sk-SK', { weekday: 'short', hour: '2-digit', minute: '2-digit', timeZone: TZ }))) + '</span></div>').join(''));
    }

    /** Vzájomné zápasy a forma oboch družstiev – načíta sa raz */
    let extraPromise = null, extraInfo = null;
    function loadExtra(force) {
        if (!M.matchID) return Promise.resolve(null);
        if (!extraPromise || force) {
            $('#loadingText').text('Načítavam vzájomné zápasy a formu…');
            extraPromise = getJson('api-h2h?id=' + encodeURIComponent(M.matchID) + (force ? '&_=' + Date.now() : ''))
                .then((j) => { extraInfo = j; return j; })
                .catch((e) => {
                    MD.toast('Vzájomné zápasy sa nepodarilo načítať: ' + e.message, 'danger');
                    extraInfo = { h2h: { played: 0, matches: [], home: {}, away: {} }, form: { home: [], away: [] }, failed: true };
                    return extraInfo;
                });
        }
        return extraPromise;
    }

    /** Zápas -> riadok pre prvok "fixtures" */
    function fixtureRow(m, ownId) {
        const d = m.date ? new Date(m.date) : null;
        const date = d && !isNaN(d) ? d.toLocaleDateString('sk-SK', { day: 'numeric', month: 'numeric', year: '2-digit', timeZone: 'Europe/Bratislava' }) : '';
        return { home: m.home.name, away: m.away.name, hs: m.home.score, as: m.away.score, played: m.home.score !== null && m.home.score !== undefined,
            time: date, day: '', own: !!ownId && (m.home.id === ownId || m.away.id === ownId), homeLogo: m.home.logo, awayLogo: m.away.logo, date: m.date, result: m.result || '' };
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
            const list = (t.players || []).filter((p) => p.apps > 0 || p.listed > 0);
            return { list: list.map((p) => ({ nr: p.nr || '', name: p.name, role: 'show', captain: !!p.captain, gk: !!p.gk, apps: p.apps, goals: p.goals, starts: p.starts, minutes: p.minutes })), coach: t.coach || '' };
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

    /* ---------------- hráč / strelec mesiaca ---------------- */
    const MONTHS_SK = ['Január', 'Február', 'Marec', 'Apríl', 'Máj', 'Jún', 'Júl', 'August', 'September', 'Október', 'November', 'December'];
    const plural = (n, one, few, many) => (n === 1 ? one : n >= 2 && n <= 4 ? few : many);
    let monthSel = M.month || '';
    const monthUnit = (kind, n) => (kind === 'goals' ? plural(n, 'gól', 'góly', 'gólov') : kind === 'minutes' ? plural(n, 'minúta', 'minúty', 'minút') : plural(n, 'zápas', 'zápasy', 'zápasov'));
    function monthsAvailable() {
        const set = new Set();
        ((teamInfo && teamInfo.players) || []).forEach((p) => (p.matches || []).forEach((m) => { if (m.date && m.score !== '') set.add(ymdOf(new Date(m.date)).slice(0, 7)); }));
        return [...set].sort().reverse();
    }
    function monthKey() {
        const list = monthsAvailable();
        if (!list.includes(monthSel)) monthSel = list[0] || '';
        return monthSel;
    }
    function monthData() {
        const key = monthKey();
        const kind = $('#monthKind').val() || M.mkind || 'goals';
        const list = ((teamInfo && teamInfo.players) || []).map((p) => {
            const ms = (p.matches || []).filter((m) => m.date && ymdOf(new Date(m.date)).slice(0, 7) === key && m.role !== 'bench');
            const value = kind === 'goals' ? ms.reduce((a, m) => a + (m.goals || 0), 0) : kind === 'minutes' ? ms.reduce((a, m) => a + (m.min || 0), 0) : ms.length;
            return { key: p.key, id: p.id, name: p.name, nr: p.nr, photo: p.photo, value, goals: ms.reduce((a, m) => a + (m.goals || 0), 0), apps: ms.length };
        }).filter((p) => p.value > 0).sort((a, b) => b.value - a.value || b.goals - a.goals || b.apps - a.apps);
        const top = list[0];
        const [y, mo] = key ? key.split('-') : ['', ''];
        const unit = top ? monthUnit(kind, top.value) : '';
        return {
            list, top,
            info: { title: kind === 'goals' ? 'STRELEC MESIACA' : 'HRÁČ MESIACA', period: key ? MONTHS_SK[+mo - 1] + ' ' + y : '', player: top ? top.name : '', value: top ? top.value : '', unit, nr: top ? top.nr : '', kind },
        };
    }
    function renderMonthCard() {
        $('#monthCard').prop('hidden', type !== 'month');
        if (type !== 'month' || !teamInfo) return;
        const list = monthsAvailable();
        const key = monthKey();
        $('#monthSel').html(list.length ? list.map((k) => { const [y, m] = k.split('-'); return '<option value="' + k + '"' + (k === key ? ' selected' : '') + '>' + MONTHS_SK[+m - 1] + ' ' + y + '</option>'; }).join('') : '<option value="">Žiadne odohraté zápasy</option>');
        const mm = monthData();
        $('#monthInfo').html(mm.list.length ? mm.list.slice(0, 6).map((p, i) => '<div class="d-flex justify-content-between py-1 border-top"><span>' + (i + 1) + '. ' + esc(p.name) + '</span><strong>' + p.value + '</strong></div>').join('') : '<div class="text-muted-2">V tomto mesiaci nikto nemá ' + ({ goals: 'gól', minutes: 'odohrané minúty', apps: 'zápas' }[mm.info.kind]) + '.</div>');
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
            const clean = (p) => ({ nr: p.nr, name: p.name, captain: p.captain, gk: p.gk, apps: p.apps, goals: p.goals, starts: p.starts, minutes: p.minutes });
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

        if (type === 'h2h' && extraInfo) {
            const h = extraInfo.h2h || {};
            const v = (o, k) => (o && o[k]) || 0;
            data.h2h = { played: h.played || 0, rows: [['VÝHRY', 'w'], ['REMÍZY', 'd'], ['PREHRY', 'l'], ['GÓLY', 'gf'], ['ŽLTÉ KARTY', 'yellow'], ['ČERVENÉ KARTY', 'red']].map(([label, k]) => ({ label, home: v(h.home, k), away: v(h.away, k) })) };
            data.fixtures = (h.matches || []).slice(0, 5).map((m) => fixtureRow(m, ''));
        }
        if (type === 'form' && extraInfo) {
            const list = ((extraInfo.form || {})[side] || []);
            const id = side === 'away' ? M.team2ID : M.team1ID;
            data.team = side === 'away' ? M.d2name : M.d1name;
            data.opponent = side === 'away' ? M.d1name : M.d2name;
            data.fixtures = list.map((m) => fixtureRow(m, id));
            data.form = list.map((m) => m.result).reverse();
        }

        if (type === 'weekend') {
            const w = weekendData();
            data.fixtures = w.rows;
            data.weekend = { title: w.title, range: w.range };
            data.team = M.d1name;
            data.startDate = w.rows[0] ? w.rows[0].date : '';
            data.table = [];
        }

        if (type === 'month') {
            const mm = monthData();
            data.month = mm.info;
            data.players = { month: mm.list, monthRest: mm.list.slice(1) };
            data.startDate = '';
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
            if (type === 'birthday') {
                const age = parseInt($('#bdAge').val(), 10) || '';
                data.birthday = { age, wish: $('#bdWish').val() || 'Prajeme veľa zdravia, radosti a gólov!' };
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
            const list = (teamInfo.players || []).filter((p) => p.apps > 0 || p.listed > 0);
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
    const remoteInfo = {};
    /** údaje hráča zo Sportnetu (fotka, rok narodenia) – načítajú sa raz */
    const playerInfo = (P) => (P && P.id ? (remoteInfo[P.id] = remoteInfo[P.id] || getJson('api-player?id=' + encodeURIComponent(P.id)).catch(() => ({}))) : Promise.resolve({}));
    /** fotka ľubovoľného hráča družstva (vlastná nahratá, inak zo Sportnetu) */
    async function photoFor(P) {
        if (!P) return null;
        const b = await MD.store.getPhoto(MD.store.photoKey(ownTeam().id || M.team, P.key));
        if (b) return MD.loadImage(URL.createObjectURL(b));
        const src = P.photo ? 'api-logo?raw=1&url=' + encodeURIComponent(P.photo) : (await playerInfo(P)).photo || '';
        return src ? MD.loadImage(src) : null;
    }
    async function loadPhoto() {
        if (photoUrl) URL.revokeObjectURL(photoUrl);
        photoUrl = null; photoImg = null;
        if (MODE !== 'player' || !playerKey) return null;
        const b = await MD.store.getPhoto(photoKey());
        if (b) { photoUrl = URL.createObjectURL(b); photoImg = await MD.loadImage(photoUrl); }
        else {
            // fotka zo Sportnetu (ak ju zápis obsahuje) – vlastná nahratá fotka má vždy prednosť
            const P = findPlayer();
            let src = P && P.photo ? 'api-logo?raw=1&url=' + encodeURIComponent(P.photo) : '';
            if (!src && P && P.id) {
                src = (await playerInfo(P)).photo || '';
            }
            if (src) { photoImg = await MD.loadImage(src); if (photoImg) $('#genPhotoImg').attr('src', photoImg.src).prop('hidden', false); }
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
        $('#birthdayBox').prop('hidden', type !== 'birthday');
        if (!P) { $('#playerStats').text(''); return; }
        if (type === 'birthday' && !$('#bdAge').data('touched')) {
            playerInfo(P).then((j) => {
                if ($('#bdAge').data('touched') || findPlayer() !== P) return;
                const age = j.birthyear ? new Date().getFullYear() - j.birthyear : '';
                $('#bdAge').val(age);
                $('#bdHint').text(j.birthyear ? 'Rok narodenia zo Sportnetu: ' + j.birthyear + '.' : 'Vek zadajte ručne – Sportnet ho pre tohto hráča neuvádza.');
                if (age && !data.birthday?.age) render();
            });
        }
        $('#playerStats').text(P.apps + ' zápasov' + (P.minutes != null ? ' · ' + P.minutes + ' min.' : '') + ' · ' + P.goals + ' gólov · ' + P.yellow + ' ŽK / ' + P.red + ' ČK');
        // zápasy pre "Hráč zápasu"
        const ms = P.matches.filter((m) => m.score !== '').reverse();
        if (!ms.find((m) => m.id === matchRef) && ms[0]) matchRef = ms[0].id;
        $('#selMatch').html(ms.map((m) => '<option value="' + esc(m.id) + '"' + (m.id === matchRef ? ' selected' : '') + '>' + esc(m.round + ' · ' + (m.home ? 'doma ' : 'vonku ') + m.opponent + ' ' + m.score + (m.goals ? ' · ' + m.goals + ' g.' : '')) + '</option>').join('') || '<option>Žiadne zápasy</option>');
        // jubileum
        // číslo jubilea: z adresy (napr. 100. zápas za kariéru), inak počet v sezóne – kým ho používateľ neprepíše
        if (!$('#msValue').data('touched')) $('#msValue').val(($('#msKind').val() || 'apps') === 'goals' ? P.goals : P.apps);
        $('#msHint').text(P.apps + ' zápasov, ' + P.goals + ' gólov');
    }

    function renderExtraCard() {
        const show = MODE === 'match' && (type === 'h2h' || type === 'form');
        $('#extraCard').prop('hidden', !show);
        if (!show) return;
        $('#extraTitle').text(type === 'h2h' ? 'Vzájomné zápasy' : 'Forma');
        $('#formSide').prop('hidden', type !== 'form');
        $('#formSide [data-side]').removeClass('active').filter('[data-side="' + side + '"]').addClass('active');
        if (!extraInfo) { $('#extraInfo').html('<span class="text-muted-2">Načítavam…</span>'); return; }
        const h = extraInfo.h2h || {};
        const res = { V: 'success', R: 'warning', P: 'danger' };
        const rows = (type === 'h2h' ? (h.matches || []).slice(0, 5) : ((extraInfo.form || {})[side] || []));
        const head = type === 'h2h'
            ? (h.played ? '<div class="mb-2">' + h.played + ' vzájomných zápasov · ' + esc(M.d1name) + ' ' + ((h.home || {}).w || 0) + ' V / ' + ((h.home || {}).d || 0) + ' R / ' + ((h.home || {}).l || 0) + ' P</div>' : '<div class="alert alert-info py-2 mb-0">Tímy spolu v tejto kategórii ešte nehrali.</div>')
            : (!rows.length ? '<div class="alert alert-info py-2 mb-0">Družstvo zatiaľ nemá odohraté zápasy.</div>' : '');
        $('#extraInfo').html(head + rows.map((m) => '<div class="d-flex justify-content-between gap-2 py-1 border-top"><span class="text-truncate">' + esc(m.home.name) + ' – ' + esc(m.away.name) + '</span><span class="text-nowrap"><strong>' + m.home.score + ':' + m.away.score + '</strong>'
            + (m.result ? ' <span class="badge text-bg-' + (res[m.result] || 'secondary') + '">' + m.result + '</span>' : '') + '</span></div>').join(''));
    }

    function renderPlayersCard() {
        const show = type === 'lineup' || SQUAD();
        $('#playersCard').prop('hidden', !show);
        $('#roundCard').prop('hidden', type !== 'round');
        renderExtraCard();
        renderWeekCard();
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
    async function render(o) {
        o = o || {};
        const seq = ++renderSeq;
        $loading.show();
        $('#btnDownload, #btnCopy, #btnSaveServer').prop('disabled', true);
        $('#templateSwitch [data-type]').removeClass('active').filter('[data-type="' + type + '"]').addClass('active');
        $('#btnEdit').attr('href', 'nastavenia?t=' + type + '&preview=1');

        try {
            const tpl = MD.store.loadTemplate(type);
            const needMatch = MODE === 'match' && ['goals', 'lineup', 'report'].includes(type);
            const theme = MD.store.brandColors();
            const [, bg, logos, brand] = await Promise.all([
                MD.ensureFonts(tpl),
                MD.store.backgroundUrl(type).then((b) => MD.loadImage(b.url).then((img) => (theme && !b.custom ? MD.recolorImage(img, theme) : img))),
                loadLogos(),
                loadBrand(),
                needMatch ? loadMatch() : Promise.resolve(),
                MODE === 'match' && ['h2h', 'form'].includes(type) ? loadExtra() : Promise.resolve(),
                MODE === 'club' ? loadClub() : Promise.resolve(),
                MODE !== 'match' ? loadTeam() : Promise.resolve(),
            ]);
            if (seq !== renderSeq) return;
            renderPlayersCard();
            applyTypeData();
            renderMonthCard();
            const T = ownTeam();

            // logá: družstvo / súper, logá v tabuľke a kole, fotka hráča
            const assets = { background: bg, logos: { home: logos.home, away: logos.away }, teamLogos: {} };
            if (MODE === 'match') {
                assets.logos.team = side === 'away' ? logos.away : logos.home;
                assets.logos.opponent = side === 'away' ? logos.home : logos.away;
                if (type === 'h2h' || type === 'form') {
                    const urls = [...new Set((data.fixtures || []).flatMap((m) => [m.homeLogo, m.awayLogo]).filter(Boolean))];
                    const imgs = await Promise.all(urls.map(logo));
                    urls.forEach((u, i) => { if (imgs[i]) assets.teamLogos[u] = imgs[i]; });
                }
            } else {
                assets.logos.team = await logo(T.logo || M.d1url);
                let oppUrl = '';
                if (type === 'motm') {
                    const c = comp();
                    const mm = c && c.matches.find((x) => x.id === matchRef);
                    if (mm) oppUrl = mm.home.id === T.id ? mm.away.logo : mm.home.logo;
                }
                assets.logos.opponent = oppUrl ? await logo(oppUrl) : null;
                const urls = [...new Set([...(type === 'table' || type === 'stats' ? (data.table || []).map((r) => r.logo) : []), ...(type === 'round' || type === 'weekend' ? (data.fixtures || []).flatMap((m) => [m.homeLogo, m.awayLogo]) : [])].filter(Boolean))];
                const imgs = await Promise.all(urls.map(logo));
                urls.forEach((u, i) => { if (imgs[i]) assets.teamLogos[u] = imgs[i]; });
                if (MODE === 'player') assets.photo = await loadPhoto();
                if (type === 'month') assets.photo = await photoFor(((data.players || {}).month || [])[0]);
            }
            if (seq !== renderSeq) return;
            MD.render(sq, tpl, data, assets);
            MD.compose(canvas, sq, { format, brand, theme: theme || MD.BASE_COLORS, teamLogo: assets.logos.team || assets.logos.home, title: storyTitle(), subtitle: storySubtitle() });
            $('#out').closest('.preview-frame').toggleClass('is-story', format === 'story');
            $('#btnDownload, #btnCopy, #btnSaveServer').prop('disabled', false);
            renderPostText();
            if (o.noSave) { $loading.hide(); return !isEmpty(); }
            if (isEmpty()) srvState('idle', 'Uloženie na server', 'Bez údajov sa obrázok automaticky neukladá – doplňte údaje alebo kliknite na Uložiť.');
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

    /** obrázok bez údajov (neukladá sa automaticky a nejde do ZIP) */
    function isEmpty() {
        return (type === 'lineup' && !data.players.start.length && !data.players.subs.length) || (SQUAD() && !data.players.squad.length)
            || (MODE === 'player' && !findPlayer()) || (type === 'table' && !(data.table || []).length) || (type === 'round' && !(data.fixtures || []).length)
            || ((type === 'h2h' || type === 'form' || type === 'weekend') && !(data.fixtures || []).length)
            || (type === 'month' && !((data.players || {}).month || []).length);
    }

    /* ---------------- story formát: nadpis a podnadpis nad / pod šablónou ---------------- */
    function storyTitle() {
        if (MODE === 'match') return [M.category, data.competition].filter(Boolean).join(' · ');
        return [data.team || M.d1name, M.category].filter(Boolean).join(' · ');
    }
    function storySubtitle() {
        const t = MD.tokens(data);
        if (MODE === 'match' && M.score1 === '' && t.date) return t.weekday + ' ' + t.date + ' · ' + t.time + (data.report && data.report.venue ? '\n' + data.report.venue : '');
        if (MODE === 'match') return [t.roundLabel, t.date].filter(Boolean).join(' · ');
        return [M.season, data.competition].filter(Boolean).join(' · ');
    }

    /* ---------------- text príspevku na Facebook / Instagram ---------------- */
    const tag = (s) => '#' + String(s || '').normalize('NFD').replace(/[\u0300-\u036f]/g, '').split(/[^a-z0-9]+/i).filter(Boolean).map((w) => w[0].toUpperCase() + w.slice(1).toLowerCase()).join('');
    function scorersTxt(sideName) {
        const by = {};
        (data.goals || []).filter((g) => g.side === sideName).forEach((g) => { const n = g.name + (g.own ? ' (vlastný)' : ''); (by[n] = by[n] || []).push(g.minute + "'"); });
        return Object.entries(by).map(([n, m]) => n + ' ' + m.join(', ')).join(', ');
    }
    function postText() {
        const t = MD.tokens(data);
        const L = [];
        const when = t.date ? t.weekday + ' ' + t.date + ' o ' + t.time : '';
        const compLine = [data.competition || M.competition, t.roundLabel].filter(Boolean).join(', ');
        const score = M.d1name + ' ' + M.score1 + ':' + M.score2 + ' ' + M.d2name;
        const fxLine = (m) => (m.label ? m.label + ': ' : '') + m.home + (m.played ? ' ' + m.hs + ':' + m.as + ' ' : ' – ') + m.away + (m.played ? '' : ' (' + [m.day, m.time].filter(Boolean).join(' ') + ')');
        const P = data.player || {};
        switch (type) {
            case 'matchday':
                L.push('⚽ MATCH DAY' + (M.category ? ' – ' + M.category : ''), '', M.d1name + ' – ' + M.d2name);
                if (when) L.push('📅 ' + when);
                if (compLine) L.push('🏆 ' + compLine);
                if (data.report && data.report.venue) L.push('📍 ' + data.report.venue);
                L.push('', 'Príďte nás povzbudiť! 💪');
                break;
            case 'result': case 'goals': case 'report': {
                L.push('🏁 ' + (type === 'goals' ? 'STRELCI' : 'VÝSLEDOK') + (M.category ? ' – ' + M.category : ''), '', score);
                const h = scorersTxt('home'), a = scorersTxt('away');
                if (h || a) L.push('', '⚽ ' + (h ? M.d1name + ': ' + h : '') + (h && a ? '\n⚽ ' : '') + (a ? M.d2name + ': ' + a : ''));
                const r = data.report || {};
                if (type === 'report') {
                    if (r.halftime) L.push('⏱️ Polčas: ' + r.halftime);
                    if (r.spectators) L.push('👥 Diváci: ' + r.spectators);
                    if (r.referee) L.push('🧑‍⚖️ Rozhodca: ' + r.referee);
                }
                if (compLine) L.push('🏆 ' + compLine);
                break;
            }
            case 'lineup': {
                const pl = data.players || {};
                L.push('📋 ZOSTAVA – ' + data.team, M.d1name + ' – ' + M.d2name + (when ? ' · ' + when : ''), '');
                if (pl.start.length) L.push('Základ: ' + pl.start.map((p) => p.name + (p.captain ? ' (C)' : '')).join(', '));
                if (pl.subs.length) L.push('Náhradníci: ' + pl.subs.map((p) => p.name).join(', '));
                if (data.coach) L.push('Tréner: ' + data.coach);
                break;
            }
            case 'h2h': {
                const h = data.h2h || {};
                const row = (k) => (h.rows || []).find((r) => r.label === k) || { home: 0, away: 0 };
                L.push('📊 VZÁJOMNÉ ZÁPASY', M.d1name + ' – ' + M.d2name, '', 'Odohraté: ' + (h.played || 0) + ' · výhry ' + row('VÝHRY').home + ' · remízy ' + row('REMÍZY').home + ' · prehry ' + row('PREHRY').home);
                (data.fixtures || []).forEach((m) => L.push('▫️ ' + fxLine(m)));
                if (when) L.push('', 'Najbližšie: ' + when);
                break;
            }
            case 'form':
                L.push('📈 FORMA – ' + data.team, (data.form || []).join(' '), '');
                (data.fixtures || []).forEach((m) => L.push('▫️ ' + fxLine(m)));
                L.push('', 'Najbližšie: ' + M.d1name + ' – ' + M.d2name + (when ? ', ' + when : ''));
                break;
            case 'squad':
                L.push('👥 SÚPISKA – ' + data.team + (M.category ? ' · ' + M.category : ''), M.season ? 'Sezóna ' + M.season : '', '', ((data.players || {}).squad || []).map((p) => (p.nr ? p.nr + '. ' : '') + p.name).join('\n'));
                break;
            case 'table': {
                const own = (data.table || []).find((r) => r.own);
                L.push('📊 TABUĽKA – ' + (data.competition || ''), data.afterRound || '', '');
                if (own) L.push(data.team + ': ' + own.pos + '. miesto, ' + own.pts + ' bodov (' + own.w + 'V ' + own.d + 'R ' + own.l + 'P, skóre ' + own.gf + ':' + own.ga + ')');
                (data.table || []).slice(0, 5).forEach((r) => L.push(r.pos + '. ' + r.name + ' – ' + r.pts + ' b.'));
                break;
            }
            case 'round':
                L.push('📅 ' + ((data.roundInfo || {}).title || 'KOLO') + ' · ' + (data.round ? data.round + '. kolo' : ''), data.competition || '', '');
                (data.fixtures || []).forEach((m) => L.push((m.own ? '⭐ ' : '▫️ ') + fxLine(m)));
                break;
            case 'stats': {
                const sc = (data.players || {}).scorers || [];
                L.push('📊 ŠTATISTIKY – ' + data.team, M.season || '', '');
                if (sc.length) L.push('⚽ Strelci:', ...sc.slice(0, 5).map((p, i) => (i + 1) + '. ' + p.name + ' – ' + p.goals));
                break;
            }
            case 'month': {
                const mm = data.month || {};
                L.push('🏅 ' + (mm.title || 'HRÁČ MESIACA') + ' – ' + data.team, mm.period || '', '');
                ((data.players || {}).month || []).slice(0, 5).forEach((p, i) => L.push((i === 0 ? '🥇 ' : (i + 1) + '. ') + p.name + ' – ' + p.value + ' ' + monthUnit(mm.kind, p.value)));
                L.push('', 'Gratulujeme! 👏');
                break;
            }
            case 'player':
                L.push('👤 ' + P.name + (P.nr ? ' #' + P.nr : ''), data.team, '', (P.apps || 0) + ' zápasov · ' + (P.goals || 0) + ' gólov' + (P.minutes ? ' · ' + P.minutes + ' minút' : ''));
                break;
            case 'motm': {
                const m = data.playerMatch || {};
                L.push('🏅 HRÁČ ZÁPASU: ' + P.name, '', [m.home, m.score, m.away].filter(Boolean).join(' '));
                if (m.goals) L.push('⚽ ' + m.goals + (m.goals === 1 ? ' gól' : m.goals < 5 ? ' góly' : ' gólov'));
                L.push('', 'Gratulujeme! 👏');
                break;
            }
            case 'milestone':
                L.push('🎉 ' + P.name + ' – ' + ((data.milestone || {}).value || '') + '. ' + ((data.milestone || {}).label || ''), '', 'Gratulujeme a ďakujeme! 👏');
                break;
            case 'birthday':
                L.push('🎂 VŠETKO NAJLEPŠIE!', '', P.name + ((data.birthday || {}).age ? ' dnes oslavuje ' + data.birthday.age + '. narodeniny' : ' má dnes narodeniny') + '. Prajeme veľa zdravia a gólov! 🎉');
                break;
            case 'weekend': {
                const w = data.weekend || {};
                L.push('🗓️ ' + (w.title || 'VÍKEND') + ' – ' + M.d1name, w.range || '', '');
                (data.fixtures || []).forEach((m) => L.push('▫️ ' + fxLine(m)));
                break;
            }
            default: L.push(M.d1name + (M.d2name ? ' – ' + M.d2name : ''));
        }
        const tags = [tag(MODE === 'match' ? (side === 'away' && type === 'lineup' ? M.d2name : M.d1name) : (data.team || M.d1name)), MODE === 'match' ? tag(M.d2name) : '', M.category ? tag(M.category) : '', '#futbal', '#futbalnet'].filter((x) => x && x !== '#');
        return L.filter((x, i, a) => !(x === '' && a[i - 1] === '')).join('\n').trim() + '\n\n' + [...new Set(tags)].join(' ');
    }
    let postFor = '';
    function renderPostText() {
        const $t = $('#postText');
        if (!$t.length) return;
        const key = type + '|' + side + '|' + playerKey + '|' + matchRef + '|' + roundIdx + '|' + weekStart;
        if ($t.data('touched') && postFor === key) return;
        postFor = key;
        $t.data('touched', false).val(postText());
    }

    const slug = (s) => String(s || '').normalize('NFD').replace(/[̀-ͯ]/g, '').replace(/[^a-z0-9]+/gi, '-').replace(/^-|-$/g, '').toLowerCase();
    const fileName = () => baseFileName().replace(/\.png$/, format === 'story' ? '_story.png' : '.png');
    function baseFileName() {
        const prefix = { matchday: 'matchday', lineup: 'zostava', result: 'vysledok', goals: 'strelci', report: 'suhrn', squad: 'supiska', table: 'tabulka', round: 'kolo', stats: 'statistiky', player: 'profil', motm: 'hrac-zapasu', milestone: 'jubileum', month: 'hrac-mesiaca', birthday: 'narodeniny', h2h: 'vzajomne', form: 'forma', weekend: 'vikend' }[type] || type;
        if (MODE === 'match') return [prefix, slug(M.d1name), 'vs', slug(M.d2name)].join('_') + ((type === 'lineup' || type === 'form') ? '_' + slug(data.team) : '') + '.png';
        if (MODE === 'player') return [prefix, slug((findPlayer() || {}).name), slug(M.d1name)].filter(Boolean).join('_') + '.png';
        if (MODE === 'club') return [prefix, slug(M.d1name), weekStart || ''].filter(Boolean).join('_') + '.png';
        return [prefix, slug(M.d1name), slug(M.category), type === 'round' ? (data.round || '') + '-kolo' : '', slug(M.season)].filter(Boolean).join('_') + '.png';
    }

    /** PNG s metadátami (Software, popis, zdroj, čas) – vo všetkých stiahnutiach, ZIP, zdieľaní aj na serveri */
    const metaTitle = () => {
        const name = ($('#templateSwitch [data-type="' + type + '"] .small').text() || type).trim();
        const who = MODE === 'match' ? M.d1name + ' – ' + M.d2name : (MODE === 'player' ? ((findPlayer() || {}).name || '') + ' · ' : '') + (M.d1name || '');
        return [name, who, M.category].filter(Boolean).join(' · ');
    };
    const toBlob = () => new Promise((res) => canvas.toBlob(res, 'image/png')).then((b) => (b ? MD.pngMeta(b, MD.imageMeta(metaTitle())) : b));

    /* ------------------------------------------------------------------ */
    /* Uloženie na server                                                  */
    /* ------------------------------------------------------------------ */
    let saveSeq = 0;
    function srvState(state, title, text, url, share) {
        const icons = { idle: 'bi-hdd-network', busy: 'spinner', ok: 'bi-cloud-check-fill', err: 'bi-exclamation-triangle-fill' };
        $('#srvIcon').attr('class', 'server-save-icon is-' + state)
            .html(state === 'busy' ? '<span class="spinner-border spinner-border-sm"></span>' : '<i class="bi ' + icons[state] + '"></i>');
        $('#srvTitle').text(title);
        $('#srvText').text(text).attr('title', text);
        $('#srvActions').prop('hidden', !url);
        if (url) { $('#srvOpen').attr('href', url); $('#srvCopy').data('url', new URL(share || url, location.href).href); }
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
                season: M.season || '', category: M.category || '', teamID: M.team || M.club || '', variant: (savedType === 'lineup' || savedType === 'form') ? side : '',
                player: MODE === 'player' && P ? P.name : '', playerKey: MODE === 'player' && P ? P.key : '',
                roundNo: savedType === 'round' ? String((data.roundInfo || {}).no || '') : '',
                mvalue: savedType === 'milestone' ? String((data.milestone || {}).value || '') : '',
                week: savedType === 'weekend' ? (weekStart || '') : '',
                month: savedType === 'month' ? monthKey() : '',
                format: format === 'story' ? 'story' : '',
            });
            const r = await fetch('api-save?' + q.toString(), { method: 'POST', headers: MD.clientHeaders({ 'Content-Type': 'image/png', 'X-MD-Mode': $('#autoSave').is(':checked') ? 'auto' : 'manual' }), body: blob });
            const txt = await r.text();
            let j;
            try { j = JSON.parse(txt); } catch (e) { throw new Error('server vrátil chybu (HTTP ' + r.status + ')'); }
            if (!r.ok || !j.ok) throw new Error(j.error || ('HTTP ' + r.status));
            if (seq !== saveSeq) return; // medzitým sa prepol typ
            const kb = Math.round(j.bytes / 1024);
            srvState('ok', j.replaced ? 'Aktualizované na serveri' : 'Uložené na serveri', j.file + ' · ' + (kb > 1024 ? (kb / 1024).toFixed(1) + ' MB' : kb + ' kB'), j.url, j.share);
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
    /* formát: štvorec 1:1 alebo story 9:16 */
    const syncFormat = () => $('#formatSwitch [data-format]').removeClass('active').filter('[data-format="' + format + '"]').addClass('active');
    syncFormat();
    $('#formatSwitch').on('click', '[data-format]', function () {
        if (this.dataset.format === format) return;
        format = this.dataset.format;
        MD.store.pref('format', format);
        setQs('format', format === 'story' ? 'story' : '');
        syncFormat();
        render();
    });

    /* text príspevku */
    $('#postText').on('input', function () { $(this).data('touched', true); });
    $('#postCopy').on('click', () => {
        navigator.clipboard.writeText($('#postText').val()).then(() => MD.toast('Text príspevku skopírovaný'), () => { $('#postText').trigger('select'); MD.toast('Skopírujte text ručne (Ctrl+C)', 'info'); });
    });
    $('#postRefresh').on('click', () => { $('#postText').data('touched', false).val(postText()); });

    /* ZIP so všetkými obrázkami skupiny */
    $('#btnZip').on('click', async function () {
        const $b = $(this);
        const types = $('#templateSwitch [data-type]').filter(function () { return !this.disabled; }).map(function () { return this.dataset.type; }).get();
        const orig = type;
        $b.prop('disabled', true);
        const files = [];
        try {
            for (let i = 0; i < types.length; i++) {
                type = types[i];
                $b.html('<span class="spinner-border spinner-border-sm me-1"></span>' + (i + 1) + ' / ' + types.length);
                $('#loadingText').text('Pripravujem obrázok ' + (i + 1) + ' z ' + types.length + '…');
                const ok = await render({ noSave: true });
                if (!ok) continue;
                const blob = await toBlob();
                if (blob) files.push({ name: fileName(), data: new Uint8Array(await blob.arrayBuffer()) });
            }
        } finally {
            type = orig;
            await render();
            $b.prop('disabled', false).html('<i class="bi bi-file-zip me-1"></i>Všetky (ZIP)');
        }
        if (!files.length) { MD.toast('Žiadny obrázok s údajmi.', 'info'); return; }
        const zipName = [MODE === 'match' ? slug(M.d1name) + '_vs_' + slug(M.d2name) : slug(M.d1name), slug(M.category), format === 'story' ? 'story' : ''].filter(Boolean).join('_') + '.zip';
        const a = document.createElement('a');
        a.href = URL.createObjectURL(MD.zip(files));
        a.download = zipName;
        document.body.appendChild(a); a.click(); a.remove();
        setTimeout(() => URL.revokeObjectURL(a.href), 4000);
        MD.toast(files.length + ' obrázkov v ZIP');
    });

    $('#templateSwitch').on('click', '[data-type]', function () {
        type = this.dataset.type;
        setQs('type', type);
        render();
    });

    const setWeek = (ws) => { weekStart = ws; setQs('week', ws); render(); };
    $('#weekPrev').on('click', () => weekStart && setWeek(addDays(weekStart, -7)));
    $('#weekNext').on('click', () => weekStart && setWeek(addDays(weekStart, 7)));
    $('#weekNow').on('click', () => setWeek(mondayOf(new Date())));
    $('#weekByCat').on('change', render);

    $('#formSide').on('click', '[data-side]', function () {
        if (this.dataset.side === side) return;
        side = this.dataset.side;
        setQs('side', side);
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
        $('#bdAge').data('touched', false);
        render();
    });
    $('#monthSel').on('change', function () { monthSel = this.value; setQs('month', monthSel); render(); });
    $('#monthKind').on('change', function () { setQs('mkind', this.value); render(); });
    $('#bdAge').on('input', function () { $(this).data('touched', true); clearTimeout(this._t); this._t = setTimeout(render, 250); });
    $('#bdWish').on('input', function () { clearTimeout(this._t); this._t = setTimeout(render, 250); });
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
    if (M.mkind) { $('#msKind').val(M.mkind); if (['goals', 'minutes', 'apps'].includes(M.mkind)) $('#monthKind').val(M.mkind); }
    if (M.age) $('#bdAge').val(M.age).data('touched', true);
    if (M.mvalue) $('#msValue').val(M.mvalue).data('touched', true);

    render();
})(jQuery);
