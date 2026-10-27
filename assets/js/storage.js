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

    // spoločné prvky šablón Zostava a Súpiska (panel pozadia team-bg.webp: x 116–884, y 96–932)
    const yellow = '#ffd166';
    // jednotný štýl nadpisov (rovnaký ako na pozadiach Match day / Výsledok / Strelci):
    // biela tučná kurzíva s ostrým červeným tieňom; podnadpis = žltý štítok
    const TITLE = { align: 'center', color: '#ffffff', bold: true, italic: true, shadow: true, shadowColor: '#e63946', shadowX: 0, shadowY: 6, shadowBlur: 0, letterSpacing: 0 };
    const PILL = { align: 'center', color: '#0b2a1a', bold: true, italic: true, boxColor: yellow, boxGlow: true, boxBorder: 3, boxBorderColor: '#ffffff', rotate: -5 };
    const bigTitle = (text) => Object.assign({ id: 'title', type: 'text', label: 'Nadpis', text, x: 500, y: 205, size: 104 }, TITLE);

    const DEFAULTS = {
        matchday: {
            name: 'Match day',
            icon: 'bi-calendar-event',
            width: 1000, height: 1000,
            defaultBackground: 'img/match-day-bg.webp',
            elements: [
                ...teamNames(),
                { id: 'day', type: 'text', label: 'Deň', text: '{day}', x: 250, y: 770, size: 180, align: 'left', bold: true, italic: true, shadow: true, shadowColor: '#e63946', shadowY: 6 },
                { id: 'monthYear', type: 'text', label: 'Mesiac a rok', text: '{month} {year}', x: 470, y: 690, size: 67, align: 'left' },
                { id: 'time', type: 'text', label: 'Čas', text: '{time}', x: 470, y: 770, size: 67, align: 'left', bold: true, color: '#ffd166' },
                competition(),
            ],
        },
        lineup: {
            name: 'Zostava',
            icon: 'bi-person-lines-fill',
            width: 1000, height: 1000,
            defaultBackground: 'img/team-bg.webp',
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
            defaultBackground: 'img/team-bg.webp',
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
        report: {
            name: 'Súhrn zápasu',
            icon: 'bi-file-text',
            width: 1000, height: 1000,
            defaultBackground: 'img/team-bg.webp',
            elements: [
                { id: 'logo1', type: 'logo', label: 'Logo domácich', team: 'home', x: 165, y: 140, size: 130 },
                { id: 'logo2', type: 'logo', label: 'Logo hostí', team: 'away', x: 705, y: 140, size: 130 },
                Object.assign({ id: 'title', type: 'text', label: 'Nadpis', text: 'SÚHRN ZÁPASU', x: 500, y: 190, size: 34 }, PILL, { rotate: -3 }),
                { id: 'score', type: 'text', label: 'Skóre', text: '{score1}:{score2}', x: 500, y: 305, size: 140, align: 'center', color: '#ffffff', bold: true, italic: true, shadow: true, shadowColor: '#e63946', shadowY: 6 },
                { id: 'half', type: 'text', label: 'Polčas', text: '(polčas {halftime})', x: 500, y: 345, size: 22, align: 'center', opacity: 0.8 },
                { id: 'name1', type: 'text', label: 'Názov domácich', text: '{d1name}', x: 230, y: 312, size: 22, align: 'center', maxWidth: 220 },
                { id: 'name2', type: 'text', label: 'Názov hostí', text: '{d2name}', x: 770, y: 312, size: 22, align: 'center', maxWidth: 220 },
                { id: 'goalsLabel', type: 'text', label: 'Nadpis gólov', text: 'GÓLY', x: 500, y: 420, size: 22, align: 'center', color: yellow, letterSpacing: 2 },
                { id: 'goalsHome', type: 'goals', label: 'Góly domácich', side: 'home', x: 470, y: 460, size: 22, gap: 32, maxWidth: 320, maxY: 640, align: 'right' },
                { id: 'goalsAway', type: 'goals', label: 'Góly hostí', side: 'away', x: 530, y: 460, size: 22, gap: 32, maxWidth: 320, maxY: 640, align: 'left' },
                { id: 'cardsLabel', type: 'text', label: 'Nadpis kariet', text: 'KARTY', x: 500, y: 690, size: 22, align: 'center', color: yellow, letterSpacing: 2 },
                { id: 'cardsHome', type: 'goals', label: 'Karty domácich', events: 'cards', side: 'home', x: 470, y: 730, size: 19, gap: 28, maxWidth: 320, maxY: 850, align: 'right' },
                { id: 'cardsAway', type: 'goals', label: 'Karty hostí', events: 'cards', side: 'away', x: 530, y: 730, size: 19, gap: 28, maxWidth: 320, maxY: 850, align: 'left' },
                { id: 'info', type: 'text', label: 'Rozhodca a diváci', text: 'Rozhodca: {referee}   ·   Diváci: {spectators}', x: 500, y: 885, size: 18, align: 'center', opacity: 0.8 },
                { id: 'competition', type: 'text', label: 'Súťaž | kolo', text: '{competition} | {roundLabel}', x: 500, y: 915, size: 16, align: 'center', opacity: 0.7 },
            ],
        },
        h2h: {
            name: 'Vzájomné zápasy',
            icon: 'bi-bar-chart-steps',
            width: 1000, height: 1000,
            defaultBackground: 'img/team-bg.webp',
            elements: [
                Object.assign({ id: 'title', type: 'text', label: 'Nadpis', text: 'VZÁJOMNÉ ZÁPASY', x: 500, y: 160, size: 76 }, TITLE),
                { id: 'logo1', type: 'logo', label: 'Logo domácich', team: 'home', x: 130, y: 190, size: 120 },
                { id: 'logo2', type: 'logo', label: 'Logo hostí', team: 'away', x: 750, y: 190, size: 120 },
                { id: 'name1', type: 'text', label: 'Názov domácich', text: '{d1name}', x: 190, y: 345, size: 22, align: 'center', maxWidth: 260 },
                { id: 'name2', type: 'text', label: 'Názov hostí', text: '{d2name}', x: 810, y: 345, size: 22, align: 'center', maxWidth: 260 },
                { id: 'played', type: 'text', label: 'Počet zápasov', text: '{h2hPlayed}', x: 500, y: 290, size: 90, align: 'center', color: yellow, shadow: true },
                { id: 'playedL', type: 'text', label: 'Popis – počet', text: 'ZÁPASOV', x: 500, y: 330, size: 20, align: 'center', opacity: 0.8, letterSpacing: 3 },
                { id: 'compare', type: 'compare', label: 'Porovnanie', x: 150, y: 380, w: 700, rowH: 56 },
                { id: 'lastL', type: 'text', label: 'Nadpis – posledné zápasy', text: 'POSLEDNÉ ZÁPASY', x: 500, y: 742, size: 20, align: 'center', color: yellow, letterSpacing: 3 },
                { id: 'fixtures', type: 'fixtures', label: 'Posledné vzájomné zápasy', x: 140, y: 760, w: 720, rowH: 40, size: 18, maxRows: 4, maxY: 925, showDate: false },
                { id: 'competition', type: 'text', label: 'Súťaž | kolo', text: '{competition} | {roundLabel} | {date}', x: 500, y: 955, size: 16, align: 'center', opacity: 0.7 },
            ],
        },
        form: {
            name: 'Forma',
            icon: 'bi-graph-up-arrow',
            width: 1000, height: 1000,
            defaultBackground: 'img/team-bg.webp',
            elements: [
                { id: 'logoTeam', type: 'logo', label: 'Logo družstva', team: 'team', x: 440, y: 120, size: 120 },
                Object.assign({ id: 'title', type: 'text', label: 'Nadpis', text: 'FORMA', x: 500, y: 335, size: 100 }, TITLE),
                { id: 'team', type: 'text', label: 'Družstvo', text: '{team}', x: 500, y: 382, size: 30, align: 'center', color: yellow, maxWidth: 700, bold: true },
                { id: 'badges', type: 'formbadges', label: 'Forma (V/R/P)', x: 500, y: 410, size: 60, gap: 16 },
                { id: 'lastL', type: 'text', label: 'Nadpis – zápasy', text: 'POSLEDNÝCH 5 ZÁPASOV', x: 500, y: 532, size: 20, align: 'center', opacity: 0.85, letterSpacing: 3 },
                { id: 'fixtures', type: 'fixtures', label: 'Posledné zápasy', x: 150, y: 548, w: 700, rowH: 64, size: 22, maxRows: 5, maxY: 880 },
                { id: 'next', type: 'text', label: 'Zápas', text: '{d1name} – {d2name} · {date} {time}', x: 500, y: 925, size: 18, align: 'center', opacity: 0.75, maxWidth: 860 },
            ],
        },
        weekend: {
            name: 'Víkend klubu',
            icon: 'bi-calendar2-week',
            width: 1000, height: 1000,
            defaultBackground: 'img/team-bg.webp',
            elements: [
                { id: 'logoTeam', type: 'logo', label: 'Logo klubu vľavo', team: 'team', x: 150, y: 118, size: 100 },
                { id: 'logoTeam2', type: 'logo', label: 'Logo klubu vpravo', team: 'team', x: 750, y: 118, size: 100 },
                Object.assign({ id: 'title', type: 'text', label: 'Nadpis', text: '{weekendTitle}', x: 500, y: 200, size: 66 }, TITLE),
                Object.assign({ id: 'club', type: 'text', label: 'Klub', text: '{team}', x: 500, y: 262, size: 30, maxWidth: 560 }, PILL, { rotate: -3 }),
                { id: 'range', type: 'text', label: 'Dátumy', text: '{weekendRange}', x: 500, y: 322, size: 24, align: 'center', opacity: 0.9, bold: true },
                { id: 'fixtures', type: 'fixtures', label: 'Zápasy družstiev', x: 140, y: 352, w: 720, rowH: 76, size: 22, maxRows: 10, maxY: 902, showDate: true, showLabel: true, highlight: '' },
                { id: 'season', type: 'text', label: 'Sezóna', text: 'Sezóna {season}', x: 500, y: 925, size: 17, align: 'center', opacity: 0.7 },
            ],
        },
        table: {
            name: 'Tabuľka',
            icon: 'bi-list-ol',
            width: 1000, height: 1000,
            defaultBackground: 'img/team-bg.webp',
            elements: [
                bigTitle('TABUĽKA'),
                { id: 'logoTeam', type: 'logo', label: 'Logo družstva', team: 'team', x: 150, y: 118, size: 100 },
                { id: 'comp', type: 'text', label: 'Súťaž', text: '{competition}', x: 500, y: 250, size: 26, align: 'center', color: yellow, maxWidth: 600 },
                { id: 'after', type: 'text', label: 'Po kole', text: '{season} · {afterRound}', x: 500, y: 288, size: 20, align: 'center', opacity: 0.8 },
                { id: 'table', type: 'table', label: 'Tabuľka', x: 160, y: 320, w: 680, rowH: 42, size: 22, header: true, maxY: 915 },
            ],
        },
        round: {
            name: 'Kolo',
            icon: 'bi-calendar-week',
            width: 1000, height: 1000,
            defaultBackground: 'img/team-bg.webp',
            elements: [
                Object.assign({ id: 'title', type: 'text', label: 'Nadpis', text: '{roundTitle}', x: 500, y: 200, size: 92 }, TITLE),
                { id: 'round', type: 'text', label: 'Kolo a dátum', text: '{roundLabel} · {date}', x: 500, y: 255, size: 26, align: 'center', color: yellow },
                { id: 'comp', type: 'text', label: 'Súťaž', text: '{competition}', x: 500, y: 292, size: 20, align: 'center', opacity: 0.8 },
                { id: 'fixtures', type: 'fixtures', label: 'Zápasy', x: 140, y: 330, w: 720, rowH: 72, size: 22, maxY: 915 },
            ],
        },
        stats: {
            name: 'Štatistiky',
            icon: 'bi-bar-chart-line',
            width: 1000, height: 1000,
            defaultBackground: 'img/team-bg.webp',
            elements: [
                { id: 'logoTeam', type: 'logo', label: 'Logo družstva', team: 'team', x: 150, y: 118, size: 100 },
                bigTitle('ŠTATISTIKY'),
                { id: 'team', type: 'text', label: 'Družstvo a sezóna', text: '{team} · {season}', x: 500, y: 255, size: 26, align: 'center', color: yellow, maxWidth: 640 },
                { id: 'summary', type: 'text', label: 'Bilancia', text: '{tpos}. miesto · {tpts} bodov · {tw}V {td}R {tl}P · skóre {tgf}:{tga}', x: 500, y: 300, size: 20, align: 'center', opacity: 0.9, maxWidth: 700 },
                { id: 'scorersLabel', type: 'text', label: 'Nadpis strelcov', text: 'STRELCI', x: 165, y: 370, size: 22, color: yellow, letterSpacing: 2 },
                { id: 'scorers', type: 'players', label: 'Strelci', source: 'scorers', x: 165, y: 412, size: 22, columns: 1, maxWidth: 320, gap: 36, maxY: 690, limit: 8, format: '{goals}  {name}', numColor: '', capMark: '', gkMark: '' },
                { id: 'appsLabel', type: 'text', label: 'Nadpis zápasov', text: 'NAJVIAC ZÁPASOV', x: 525, y: 370, size: 22, color: yellow, letterSpacing: 2 },
                { id: 'apps', type: 'players', label: 'Najviac zápasov', source: 'apps', x: 525, y: 412, size: 22, columns: 1, maxWidth: 320, gap: 36, maxY: 690, limit: 8, format: '{apps}  {name}', numColor: '', capMark: '', gkMark: '' },
                { id: 'cardsLabel', type: 'text', label: 'Nadpis kariet', text: 'KARTY (ŽK / ČK)', x: 165, y: 740, size: 20, color: yellow, letterSpacing: 2 },
                { id: 'cards', type: 'players', label: 'Karty', source: 'cards', x: 165, y: 778, size: 19, columns: 2, maxWidth: 330, colGap: 20, gap: 30, maxY: 900, limit: 8, format: '{yellow}/{red}  {name}', numColor: '', capMark: '', gkMark: '' },
            ],
        },
        player: {
            name: 'Profil hráča',
            icon: 'bi-person-vcard',
            width: 1000, height: 1000,
            defaultBackground: 'img/team-bg.webp',
            elements: [
                { id: 'logoTeam', type: 'logo', label: 'Logo družstva', team: 'team', x: 150, y: 118, size: 100 },
                Object.assign({ id: 'title', type: 'text', label: 'Nadpis', text: 'PROFIL HRÁČA', x: 500, y: 178, size: 34 }, PILL, { rotate: -3 }),
                { id: 'photo', type: 'photo', label: 'Fotka hráča', x: 350, y: 205, w: 300, h: 300, shape: 'circle', borderWidth: 6, borderColor: '#ffffff', shadow: true },
                Object.assign({ id: 'nr', type: 'text', label: 'Číslo (pri fotke)', text: '{playerNr}', x: 640, y: 500, size: 76, showIf: 'photo' }, TITLE),
                { id: 'name', type: 'text', label: 'Meno', text: '{player}', x: 500, y: 590, size: 60, align: 'center', uppercase: true, maxWidth: 700, shadow: true },
                { id: 'team', type: 'text', label: 'Družstvo', text: '{team} · {category} · {season}', x: 500, y: 636, size: 22, align: 'center', color: yellow, maxWidth: 700 },
                { id: 'shape', type: 'shape', label: 'Podklad štatistík', x: 160, y: 690, w: 680, h: 160, color: '#000000', opacity: 0.35, radius: 20 },
                { id: 's1', type: 'text', label: 'Zápasy', text: '{apps}', x: 245, y: 785, size: 66, align: 'center' },
                { id: 's2', type: 'text', label: 'Góly', text: '{goalsP}', x: 415, y: 785, size: 66, align: 'center', color: yellow },
                { id: 's3', type: 'text', label: 'Minúty', text: '{minutes}', x: 585, y: 785, size: 66, align: 'center' },
                { id: 's4', type: 'text', label: 'Karty', text: '{yellow}/{red}', x: 755, y: 785, size: 66, align: 'center' },
                { id: 'l1', type: 'text', label: 'Popis – zápasy', text: 'ZÁPASY', x: 245, y: 825, size: 18, align: 'center', opacity: 0.8, letterSpacing: 2 },
                { id: 'l2', type: 'text', label: 'Popis – góly', text: 'GÓLY', x: 415, y: 825, size: 18, align: 'center', opacity: 0.8, letterSpacing: 2 },
                { id: 'l3', type: 'text', label: 'Popis – minúty', text: 'MINÚTY', x: 585, y: 825, size: 18, align: 'center', opacity: 0.8, letterSpacing: 2 },
                { id: 'l4', type: 'text', label: 'Popis – karty', text: 'ŽK / ČK', x: 755, y: 825, size: 18, align: 'center', opacity: 0.8, letterSpacing: 2 },
                { id: 'competition', type: 'text', label: 'Súťaž', text: '{competition}', x: 500, y: 905, size: 18, align: 'center', opacity: 0.7 },
            ],
        },
        motm: {
            name: 'Hráč zápasu',
            icon: 'bi-award',
            width: 1000, height: 1000,
            defaultBackground: 'img/team-bg.webp',
            elements: [
                { id: 'logoTeam', type: 'logo', label: 'Logo družstva', team: 'team', x: 150, y: 118, size: 100 },
                { id: 'logoOpp', type: 'logo', label: 'Logo súpera', team: 'opponent', x: 750, y: 118, size: 100 },
                Object.assign({ id: 'title', type: 'text', label: 'Nadpis', text: 'HRÁČ ZÁPASU', x: 500, y: 200, size: 96 }, TITLE),
                { id: 'photo', type: 'photo', label: 'Fotka hráča', x: 360, y: 240, w: 280, h: 280, shape: 'circle', borderWidth: 6, borderColor: '#ffd166', shadow: true },
                { id: 'name', type: 'text', label: 'Meno', text: '{player}', x: 500, y: 610, size: 62, align: 'center', uppercase: true, maxWidth: 700, shadow: true },
                { id: 'nr', type: 'text', label: 'Číslo', text: '#{playerNr}', x: 500, y: 660, size: 28, align: 'center', color: yellow },
                { id: 'match', type: 'text', label: 'Zápas', text: '{matchHome}  {matchScore}  {matchAway}', x: 500, y: 745, size: 28, align: 'center', maxWidth: 700 },
                { id: 'goals', type: 'text', label: 'Góly v zápase', text: 'Góly: {matchGoals}', x: 500, y: 795, size: 24, align: 'center', color: yellow },
                { id: 'competition', type: 'text', label: 'Súťaž | kolo', text: '{competition} | {roundLabel}', x: 500, y: 905, size: 18, align: 'center', opacity: 0.7 },
            ],
        },
        milestone: {
            name: 'Jubileum',
            icon: 'bi-trophy',
            width: 1000, height: 1000,
            defaultBackground: 'img/team-bg.webp',
            elements: [
                { id: 'logoTeam', type: 'logo', label: 'Logo družstva', team: 'team', x: 150, y: 118, size: 100 },
                { id: 'logoTeam2', type: 'logo', label: 'Logo vpravo', team: 'team', x: 750, y: 118, size: 100 },
                Object.assign({ id: 'title', type: 'text', label: 'Nadpis', text: 'JUBILEUM', x: 500, y: 200, size: 104 }, TITLE),
                { id: 'photo', type: 'photo', label: 'Fotka hráča', x: 385, y: 235, w: 230, h: 230, shape: 'circle', borderWidth: 6, borderColor: '#ffffff', shadow: true },
                { id: 'value', type: 'text', label: 'Číslo jubilea', text: '{milestone}.', x: 500, y: 650, size: 170, align: 'center', color: yellow, shadow: true },
                { id: 'label', type: 'text', label: 'Text jubilea', text: '{milestoneLabel}', x: 500, y: 715, size: 34, align: 'center', uppercase: true, maxWidth: 700 },
                { id: 'name', type: 'text', label: 'Meno', text: '{player}', x: 500, y: 790, size: 52, align: 'center', maxWidth: 700, shadow: true },
                { id: 'congrats', type: 'text', label: 'Gratulácia', text: 'Gratulujeme!', x: 500, y: 850, size: 28, align: 'center', color: yellow },
                { id: 'season', type: 'text', label: 'Sezóna', text: '{category} · {season}', x: 500, y: 905, size: 18, align: 'center', opacity: 0.7 },
            ],
        },
        month: {
            name: 'Hráč mesiaca',
            icon: 'bi-star',
            width: 1000, height: 1000,
            defaultBackground: 'img/team-bg.webp',
            elements: [
                { id: 'logoTeam', type: 'logo', label: 'Logo družstva', team: 'team', x: 150, y: 118, size: 100 },
                Object.assign({ id: 'title', type: 'text', label: 'Nadpis', text: '{monthTitle}', x: 520, y: 200, size: 70, maxWidth: 560 }, TITLE),
                Object.assign({ id: 'period', type: 'text', label: 'Obdobie', text: '{monthPeriod}', x: 640, y: 262, size: 30, uppercase: true }, PILL),
                { id: 'photo', type: 'photo', label: 'Fotka hráča', x: 385, y: 285, w: 230, h: 230, shape: 'circle', borderWidth: 6, borderColor: '#ffd166', shadow: true, fallbackText: '{monthNr}' },
                { id: 'name', type: 'text', label: 'Meno', text: '{monthPlayer}', x: 500, y: 600, size: 58, align: 'center', uppercase: true, maxWidth: 700, shadow: true },
                Object.assign({ id: 'value', type: 'text', label: 'Hodnota', text: '{monthValue}', x: 500, y: 720, size: 110 }, TITLE, { color: yellow }),
                { id: 'unit', type: 'text', label: 'Jednotka', text: '{monthUnit}', x: 500, y: 765, size: 26, align: 'center', uppercase: true, letterSpacing: 3, opacity: 0.9 },
                { id: 'restLabel', type: 'text', label: 'Nadpis ďalších', text: 'ĎALŠÍ V PORADÍ', x: 500, y: 828, size: 18, align: 'center', color: yellow, letterSpacing: 2 },
                { id: 'rest', type: 'players', label: 'Ďalší hráči', source: 'monthRest', x: 250, y: 880, size: 21, columns: 2, maxWidth: 240, colGap: 30, gap: 30, maxY: 915, limit: 4, format: '{value}  {name}', numColor: '', capMark: '', gkMark: '' },
                { id: 'team', type: 'text', label: 'Družstvo', text: '{team} · {category}', x: 500, y: 950, size: 18, align: 'center', opacity: 0.7 },
            ],
        },
        birthday: {
            name: 'Narodeniny',
            icon: 'bi-cake2',
            width: 1000, height: 1000,
            defaultBackground: 'img/team-bg.webp',
            elements: [
                { id: 'logoTeam', type: 'logo', label: 'Logo družstva', team: 'team', x: 150, y: 118, size: 100 },
                Object.assign({ id: 'title', type: 'text', label: 'Nadpis', text: 'VŠETKO NAJLEPŠIE', x: 520, y: 200, size: 70, maxWidth: 560 }, TITLE),
                { id: 'photo', type: 'photo', label: 'Fotka hráča', x: 360, y: 240, w: 280, h: 280, shape: 'circle', borderWidth: 6, borderColor: '#ffd166', shadow: true },
                Object.assign({ id: 'age', type: 'text', label: 'Vek', text: '{age}.', x: 650, y: 525, size: 120, rotate: -6 }, TITLE, { color: yellow }),
                Object.assign({ id: 'label', type: 'text', label: 'Text', text: 'NARODENINY', x: 500, y: 600, size: 34 }, PILL, { rotate: -3 }),
                { id: 'name', type: 'text', label: 'Meno', text: '{player}', x: 500, y: 700, size: 64, align: 'center', uppercase: true, maxWidth: 720, shadow: true },
                { id: 'wish', type: 'text', label: 'Prianie', text: '{birthdayWish}', x: 500, y: 770, size: 28, align: 'center', maxWidth: 720, lineHeight: 1.3, opacity: 0.95 },
                { id: 'team', type: 'text', label: 'Družstvo', text: '{team} · {category}', x: 500, y: 905, size: 18, align: 'center', opacity: 0.7 },
            ],
        },
        result: {
            name: 'Výsledok',
            icon: 'bi-trophy',
            width: 1000, height: 1000,
            defaultBackground: 'img/match-result-bg.webp',
            elements: [
                ...teamNames(),
                { id: 'score', type: 'text', label: 'Skóre', text: '{score1}:{score2}', x: 500, y: 770, size: 240, align: 'center', bold: true, italic: true, shadow: true, shadowColor: '#e63946', shadowY: 8 },
                { id: 'day', type: 'text', label: 'Deň', text: '{day}', x: 340, y: 892, size: 107, align: 'left', bold: true },
                { id: 'monthYear', type: 'text', label: 'Mesiac a rok', text: '{month} {year}', x: 490, y: 850, size: 40, align: 'left' },
                { id: 'time', type: 'text', label: 'Čas', text: '{time}', x: 490, y: 890, size: 40, align: 'left' },
                competition(),
            ],
        },
        goals: {
            name: 'Strelci',
            icon: 'bi-list-ol',
            width: 1000, height: 1000,
            defaultBackground: 'img/match-result-goals-bg.webp',
            elements: [
                ...teamNames(),
                { id: 'score', type: 'text', label: 'Skóre', text: '{score1}:{score2}', x: 500, y: 660, size: 120, align: 'center', bold: true, italic: true, shadow: true, shadowColor: '#e63946', shadowY: 5 },
                { id: 'goalsHome', type: 'goals', label: 'Strelci domácich', side: 'home', x: 130, y: 615, size: 21, gap: 34, lineHeight: 1.2, maxWidth: 270, maxY: 925, align: 'left', autoFit: true, minScale: 0.6 },
                { id: 'goalsAway', type: 'goals', label: 'Strelci hostí', side: 'away', x: 630, y: 615, size: 21, gap: 34, lineHeight: 1.2, maxWidth: 270, maxY: 925, align: 'left', autoFit: true, minScale: 0.6 },
                competition(),
            ],
        },
    };

    /** Spoločné predvolené vlastnosti podľa typu prvku */
    const TYPE_DEFAULTS = {
        text: { text: 'Text', size: 32, font: 'Barlow Condensed', color: '#ffffff', align: 'left', maxWidth: 0, lineHeight: 1.25, uppercase: false, bold: false, italic: false, letterSpacing: 0, shadow: false, strokeWidth: 0, strokeColor: '#000000', opacity: 1 },
        logo: { team: 'home', size: 120, shadow: false, opacity: 1 },
        goals: { events: 'goals', side: 'home', size: 21, font: 'Barlow Condensed', color: '#ffffff', align: 'left', maxWidth: 270, gap: 34, lineHeight: 1.2, maxY: 925, autoFit: true, minScale: 0.6, format: "{min}' {name}", ownPrefix: '(vl.) ', uppercase: false, bold: false, shadow: false, strokeWidth: 0, strokeColor: '#000000', opacity: 1, ownColor: '' },
        shape: { w: 300, h: 60, color: '#000000', opacity: 0.5, radius: 12 },
        photo: { w: 300, h: 300, shape: 'circle', fit: 'cover', borderWidth: 6, borderColor: '#ffffff', bgColor: '#0b2a1a', fallbackText: '{playerNr}', fallbackColor: '#e63946', fallbackColor2: '#8d0f1a', font: 'Barlow Condensed', color: '#ffffff', shadow: true, opacity: 1, radius: 24 },
        table: { w: 680, rowH: 42, size: 22, font: 'Barlow Condensed', color: '#ffffff', headColor: '#ffd166', ownColor: '#ffffff', ptsColor: '#ffd166', highlight: 'rgba(230,57,70,0.85)', stripe: 'rgba(255,255,255,0.05)', header: true, showLogos: true, columns: 'p,w,d,l,score,pts', maxRows: 20, maxY: 915, autoFit: true, ownBold: true, uppercase: false, headName: 'Družstvo', opacity: 1 },
        fixtures: { showLabel: false, labelColor: '#ffd166', w: 720, rowH: 72, size: 22, font: 'Barlow Condensed', color: '#ffffff', pillColor: '#e63946', pillColor2: 'rgba(255,255,255,0.14)', pillText: '#ffffff', highlight: 'rgba(255,209,102,0.16)', showLogos: true, showDate: false, maxRows: 12, maxY: 915, autoFit: true, uppercase: false, opacity: 1 },
        compare: { w: 700, rowH: 56, size: 22, font: 'Barlow Condensed', color: '#ffffff', labelColor: '#ffffff', barHome: '#e63946', barAway: '#ffd166', barBg: 'rgba(255,255,255,0.15)', barH: 8, uppercase: true, opacity: 1 },
        formbadges: { size: 64, gap: 18, font: 'Barlow Condensed', color: '#ffffff', winColor: '#22a06b', drawColor: '#f2a516', lossColor: '#d93a3a', max: 5, opacity: 1 },
        players: { limit: 0, source: 'start', size: 26, font: 'Barlow Condensed', color: '#ffffff', numColor: '#ffd166', align: 'left', letterSpacing: 0, italic: false, columns: 2, maxWidth: 330, colGap: 20, gap: 44, lineHeight: 1.2, maxY: 900, autoFit: true, minScale: 0.6, format: '{nr}  {name}', capMark: ' (C)', gkMark: ' (B)', emptyText: '', uppercase: false, bold: false, shadow: false, strokeWidth: 0, strokeColor: '#000000', opacity: 1 },
    };
    MD.TYPE_DEFAULTS = TYPE_DEFAULTS;

    MD.TEMPLATE_IDS = ['matchday', 'lineup', 'result', 'goals', 'report', 'h2h', 'form', 'squad', 'table', 'round', 'stats', 'month', 'player', 'motm', 'milestone', 'birthday', 'weekend'];
    /** Typy viazané na zápas / družstvo / hráča */
    MD.TYPE_GROUPS = { match: ['matchday', 'lineup', 'result', 'goals', 'report', 'h2h', 'form'], team: ['squad', 'table', 'round', 'stats', 'month'], player: ['player', 'motm', 'milestone', 'birthday'], club: ['weekend'] };

    const clone = (o) => JSON.parse(JSON.stringify(o));

    function normalizeElement(el) {
        const out = Object.assign({ visible: true }, TYPE_DEFAULTS[el.type] || {}, el);
        return out;
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
    // Jednorazový prechod na jednotné písmo: uložené šablóny s písmom Biome (komerčné, v repozitári
    // nie je – bez neho sa kreslilo náhradným písmom) a pôvodnými červenými nadpismi dostanú nový štýl.
    (function migrateFonts() {
        const saved = readLS(LS_KEY, null);
        if (!saved || readLS('matchday.migrated.font', false)) return;
        Object.values(saved).forEach((tpl) => (tpl && tpl.elements || []).forEach((el) => {
            if (el.font === 'Biome') el.font = 'Barlow Condensed';
            if (el.type === 'text' && el.color === '#e63946' && el.strokeColor === '#ffffff' && el.strokeWidth > 0) Object.assign(el, TITLE, { strokeWidth: 0 });
        }));
        writeLS(LS_KEY, saved);
        writeLS('matchday.migrated.font', true);
    })();


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
    /* Fotky hráčov (IndexedDB, kľúč "photo:<družstvo>:<hráč>")            */
    /* ------------------------------------------------------------------ */
    store.photoKey = (team, player) => 'photo:' + String(team || '') + ':' + String(player || '');
    store.getPhoto = (key) => idb('readonly', (s) => s.get(key)).then((b) => b || null).catch(() => null);
    store.setPhoto = (key, blob) => idb('readwrite', (s) => s.put(blob, key)).catch(() => {
        throw new Error('Prehliadač nepodporuje ukladanie fotiek (IndexedDB).');
    });
    store.removePhoto = (key) => idb('readwrite', (s) => s.delete(key)).catch(() => null);
    store.listPhotos = function () {
        return db().then((d) => new Promise((resolve) => {
            if (!d) return resolve([]);
            const out = [];
            const req = d.transaction(DB_STORE, 'readonly').objectStore(DB_STORE).openCursor();
            req.onsuccess = () => {
                const c = req.result;
                if (!c) return resolve(out);
                if (String(c.key).startsWith('photo:')) out.push({ key: String(c.key), blob: c.value });
                c.continue();
            };
            req.onerror = () => resolve(out);
        }));
    };
    store.clearPhotos = async function () { for (const p of await store.listPhotos()) await store.removePhoto(p.key); };

    /* ------------------------------------------------------------------ */
    /* Značka klubu – logo, pás sponzorov a farby (IndexedDB "brand:*")    */
    /* ------------------------------------------------------------------ */
    const LS_BRAND = 'matchday.brand.v1';
    store.BRAND_DEFAULTS = {
        colors: { on: false, primary: '#e63946', accent: '#ffd166', base: '#0b2a1a' },
        logo: { on: true, pos: 'tr', size: 110, opacity: 1 },
        sponsors: { on: true, height: 92, strip: true },
        story: { top: 'logo' },
    };
    store.brand = function (value) {
        if (value !== undefined) { writeLS(LS_BRAND, value); return value; }
        const b = readLS(LS_BRAND, {}) || {};
        const D = store.BRAND_DEFAULTS;
        return { colors: Object.assign({}, D.colors, b.colors), logo: Object.assign({}, D.logo, b.logo), sponsors: Object.assign({}, D.sponsors, b.sponsors), story: Object.assign({}, D.story, b.story) };
    };
    store.getBrandImage = (k) => idb('readonly', (s) => s.get('brand:' + k)).then((b) => b || null).catch(() => null);
    store.setBrandImage = (k, blob) => idb('readwrite', (s) => s.put(blob, 'brand:' + k)).catch(() => {
        throw new Error('Prehliadač nepodporuje ukladanie obrázkov (IndexedDB).');
    });
    store.removeBrandImage = (k) => idb('readwrite', (s) => s.delete('brand:' + k)).catch(() => null);
    /** { logo: Image|null, sponsors: Image|null, cfg } – na vloženie do každého obrázka */
    store.loadBrand = async function () {
        const cfg = store.brand();
        const load = async (k) => { const b = await store.getBrandImage(k); return b ? MD.loadImage(URL.createObjectURL(b)) : null; };
        const [logo, sponsors] = await Promise.all([load('logo'), load('sponsors')]);
        return { logo, sponsors, cfg };
    };
    /** farby klubu, ak sú zapnuté (inak null) */
    store.brandColors = function () { const c = store.brand().colors; return c.on ? c : null; };
    /** Zmenší fotku (max. 1200 px, JPEG), aby úložisko prehliadača nezaplnili veľké súbory z mobilu */
    store.shrinkImage = function (file, max) {
        max = max || 1200;
        return new Promise((resolve) => {
            const url = URL.createObjectURL(file);
            const img = new Image();
            img.onload = () => {
                const r = Math.min(1, max / Math.max(img.naturalWidth, img.naturalHeight));
                const c = document.createElement('canvas');
                c.width = Math.round(img.naturalWidth * r); c.height = Math.round(img.naturalHeight * r);
                c.getContext('2d').drawImage(img, 0, 0, c.width, c.height);
                URL.revokeObjectURL(url);
                // PNG s priehľadnosťou (napr. hráč bez pozadia) ostane PNG
                const png = /png|webp/.test(file.type);
                c.toBlob((b) => resolve(b || file), png ? 'image/png' : 'image/jpeg', 0.9);
            };
            img.onerror = () => { URL.revokeObjectURL(url); resolve(file); };
            img.src = url;
        });
    };

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
        out.photos = {};
        for (const f of await store.listPhotos()) out.photos[f.key] = await blobToDataURL(f.blob);
        out.players = readLS('matchday.players.v1', {});
        out.brand = { cfg: readLS(LS_BRAND, null), images: {} };
        for (const k of ['logo', 'sponsors']) { const b = await store.getBrandImage(k); if (b) out.brand.images[k] = await blobToDataURL(b); }
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
        for (const [key, data] of Object.entries(json.photos || {})) {
            if (key.startsWith('photo:')) await store.setPhoto(key, await dataURLToBlob(data)).catch(() => null);
        }
        if (json.brand && typeof json.brand === 'object') {
            if (json.brand.cfg) writeLS(LS_BRAND, json.brand.cfg);
            for (const [k, d] of Object.entries(json.brand.images || {})) if (k === 'logo' || k === 'sponsors') await store.setBrandImage(k, await dataURLToBlob(d)).catch(() => null);
        }
        if (json.players && typeof json.players === 'object') writeLS('matchday.players.v1', Object.assign(readLS('matchday.players.v1', {}) || {}, json.players));
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
