/* Generátor – vykreslí zvolenú šablónu s dátami zápasu a umožní stiahnutie PNG */
(function ($) {
    'use strict';
    const MD = window.MD;
    const M = window.MD_MATCH || {};
    let type = window.MD_TYPE || 'matchday';

    const canvas = document.getElementById('out');
    const $loading = $('#loading');
    const proxied = (u) => (u ? 'api-logo?url=' + encodeURIComponent(u) : '');

    const data = {
        d1name: M.d1name, d2name: M.d2name, startDate: M.startDate,
        competition: M.competition, round: M.round, score1: M.score1, score2: M.score2,
        goals: [],
    };

    let logosPromise = null;
    let goalsPromise = null;

    function loadLogos() {
        if (!logosPromise) {
            logosPromise = Promise.all([MD.loadImage(proxied(M.d1url)), MD.loadImage(proxied(M.d2url))])
                .then(([home, away]) => ({ home, away }));
        }
        return logosPromise;
    }

    function loadGoals() {
        if (!goalsPromise) {
            if (!M.matchID) return Promise.resolve([]);
            goalsPromise = fetch('api-match?id=' + encodeURIComponent(M.matchID) + '&home=' + encodeURIComponent(M.team1ID))
                .then(async (r) => {
                    const txt = await r.text();
                    try { return JSON.parse(txt); } catch (e) {
                        // server poslal niečo iné ako JSON (napr. chybovú stránku PHP)
                        const plain = txt.replace(/<[^>]+>/g, ' ').replace(/\s+/g, ' ').trim().slice(0, 220);
                        throw new Error('server vrátil chybu (HTTP ' + r.status + '): ' + plain);
                    }
                })
                .then((j) => {
                    if (j.php_warnings) console.warn('PHP:', j.php_warnings);
                    if (j.error) throw new Error(j.error);
                    data.goals = j.goals || [];
                    renderGoalsInfo();
                    return data.goals;
                })
                .catch((e) => {
                    MD.toast('Strelcov sa nepodarilo načítať: ' + e.message, 'danger');
                    return [];
                });
        }
        return goalsPromise;
    }

    function renderGoalsInfo() {
        const cols = { home: [], away: [] };
        data.goals.forEach((g) => cols[g.side].push(g));
        const $c = $('#goalsCols').empty();
        [['home', M.d1name], ['away', M.d2name]].forEach(([side, name]) => {
            const $col = $('<div class="col-6"><div class="fw-semibold text-truncate mb-1"></div><ul class="list-unstyled mb-0"></ul></div>');
            $col.find('div').text(name);
            if (!cols[side].length) $col.find('ul').append('<li class="text-muted-2">–</li>');
            cols[side].forEach((g) => {
                $('<li></li>').text(g.minute + "' " + (g.own ? '(vl.) ' : '') + g.name).appendTo($col.find('ul'));
            });
            $c.append($col);
        });
        $('#goalsInfo').prop('hidden', false);
    }

    async function render() {
        $loading.show();
        $('#btnDownload, #btnCopy, #btnSaveServer').prop('disabled', true);
        $('#templateSwitch [data-type]').removeClass('active').filter('[data-type="' + type + '"]').addClass('active');
        $('#btnEdit').attr('href', 'nastavenia?t=' + type + '&preview=1');

        try {
            const tpl = MD.store.loadTemplate(type);
            const [, bg, logos] = await Promise.all([
                MD.ensureFonts(tpl),
                MD.store.backgroundUrl(type).then((b) => MD.loadImage(b.url)),
                loadLogos(),
                type === 'goals' ? loadGoals() : Promise.resolve(),
            ]);
            MD.render(canvas, tpl, data, { background: bg, logos: logos });
            $('#btnDownload, #btnCopy, #btnSaveServer').prop('disabled', false);
            if ($('#autoSave').is(':checked')) saveToServer();
            else srvState('idle', 'Uloženie na server', 'Automatické ukladanie je vypnuté – kliknite na Uložiť.');
        } catch (e) {
            console.error(e);
            MD.toast('Obrázok sa nepodarilo vygenerovať.', 'danger');
        }
        $loading.hide();

        // údaje pre náhľad v editore
        try {
            sessionStorage.setItem('matchday.preview', JSON.stringify(Object.assign({}, data, {
                logoHome: proxied(M.d1url), logoAway: proxied(M.d2url),
            })));
        } catch (e) { /* ignore */ }
    }

    function fileName() {
        const slug = (s) => String(s || '').normalize('NFD').replace(/[̀-ͯ]/g, '').replace(/[^a-z0-9]+/gi, '-').replace(/^-|-$/g, '').toLowerCase();
        return [type, slug(M.d1name), 'vs', slug(M.d2name)].join('_') + '.png';
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
                type: savedType, d1name: M.d1name || '', d2name: M.d2name || '', startDate: M.startDate || '',
                competition: M.competition || '', round: M.round || '', score1: M.score1 || '', score2: M.score2 || '', matchID: M.matchID || '',
            });
            const r = await fetch('api-save?' + q.toString(), { method: 'POST', headers: MD.clientHeaders({ 'Content-Type': 'image/png', 'X-MD-Info': await MD.clientInfoHeader(), 'X-MD-Mode': $('#autoSave').is(':checked') ? 'auto' : 'manual' }), body: blob });
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
            navigator.share({ files: [file], title: M.d1name + ' vs ' + M.d2name }).catch(() => {});
        });
    }

    render();
})(jQuery);
