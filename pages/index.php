<?php
	try {
		$data = getJsonFromUrl(API_SEASONS);
	} catch (Exception $e) {
		$data = ['error' => true];
	}
	$seasons = !empty($data['seasons']) ? array_reverse($data['seasons']) : [];

	$current = md_default_season();
	add_layout("header", array("title" => "Match day – generátor"));
?>

<section class="hero hero-home">
	<div class="container-xxl">
		<span class="hero-eyebrow mb-3"><i class="bi bi-lightning-charge-fill text-warning"></i> Grafiky k zápasom za pár sekúnd</span>
		<h1 class="mb-3">Match <span class="accent">Day</span> generátor</h1>
		<p class="lead mb-4">Nájdite svoj klub, vyberte zápas a stiahnite si hotový obrázok.</p>

		<form class="gsearch gsearch-hero mb-3" role="search" autocomplete="off">
			<i class="bi bi-search gsearch-icon" aria-hidden="true"></i>
			<input type="search" class="form-control gsearch-input" id="heroSearch" placeholder="Klub, obec alebo hráč…" aria-label="Hľadať klub alebo hráča" aria-expanded="false" aria-controls="gsMenuHero" data-gsearch data-gsearch-main data-season-select="#season" autofocus>
			<div class="gsearch-menu" id="gsMenuHero" role="listbox" hidden></div>
		</form>
		<?php if ($seasons): ?>
		<label class="hero-season" for="season">Sezóna
			<select class="form-select form-select-sm" id="season">
				<?php foreach ($seasons as $value): ?>
					<option value="<?= plain($value["name"]) ?>" <?= $value["name"] === $current ? 'selected' : '' ?>><?= plain($value["name"]) ?></option>
				<?php endforeach; ?>
			</select>
		</label>
		<?php else: ?>
			<div class="small text-warning"><i class="bi bi-exclamation-triangle me-1"></i>Zoznam sezón sa nepodarilo načítať – použije sa aktuálna sezóna.</div>
		<?php endif; ?>
	</div>
</section>

<div class="container-xxl overlap">
	<div class="card-app mb-4 fav-card" id="favWrap" hidden>
		<div class="card-body">
			<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
				<a class="fav-club text-decoration-none" id="favLink">
					<img class="fav-logo" id="favLogo" alt="" hidden>
					<span><span class="small text-muted-2 d-block"><i class="bi bi-star-fill text-warning me-1"></i>Môj klub</span><span class="fav-name" id="favName"></span></span>
				</a>
				<div class="d-flex flex-wrap gap-2">
					<a class="btn btn-sm btn-warning rounded-pill fw-semibold" id="favWeekend"><i class="bi bi-calendar2-week me-1"></i>Víkend klubu</a>
					<button type="button" class="btn btn-sm btn-link text-muted-2 text-decoration-none" id="favRemove" title="Zrušiť obľúbený klub"><i class="bi bi-x-lg"></i></button>
				</div>
			</div>
			<div class="row g-3" id="favBody"><div class="col-12 small text-muted-2"><span class="spinner-border spinner-border-sm me-1"></span>Načítavam zápasy klubu…</div></div>
		</div>
	</div>

	<div class="card-app mb-4" id="recentWrap" hidden>
		<div class="card-body">
			<div class="d-flex justify-content-between align-items-center mb-3">
				<h2 class="h6 mb-0 fw-semibold"><i class="bi bi-clock-history me-1 text-primary"></i> Vaše kluby</h2>
				<button type="button" class="btn btn-link btn-sm text-decoration-none p-0" id="recentClear">Vymazať</button>
			</div>
			<div id="recentList" class="d-flex flex-wrap gap-2"></div>
		</div>
	</div>

	<div class="card-app">
		<div class="card-body">
			<div class="row g-4">
				<div class="col-md-4"><div class="step-card"><span class="step-no">1</span><div><div class="fw-semibold">Nájdite klub</div><div class="small text-muted-2">Napíšte názov do hľadania. Hľadať môžete kedykoľvek aj hore v menu (klávesa <kbd>/</kbd>).</div></div></div></div>
				<div class="col-md-4"><div class="step-card"><span class="step-no">2</span><div><div class="fw-semibold">Vyberte zápas</div><div class="small text-muted-2">Najbližší a posledný zápas uvidíte hneď navrchu, ostatné v zozname.</div></div></div></div>
				<div class="col-md-4"><div class="step-card"><span class="step-no">3</span><div><div class="fw-semibold">Stiahnite obrázok</div><div class="small text-muted-2">Match day, zostava, výsledok, strelci, tabuľka, hráč… Vzhľad upravíte v <a href="<?= url('nastavenia') ?>">šablónach</a>.</div></div></div></div>
			</div>
		</div>
	</div>
</div>

<script>
$(function () {
	const MD = window.MD;
	const base = (window.MD_BASE || '').replace(/\/$/, '');
	function recent() { return MD.store.pref('recent') || []; }
	function renderRecent() {
		const list = recent();
		$('#recentWrap').prop('hidden', !list.length);
		const $l = $('#recentList').empty();
		list.forEach((r, i) => {
			const $a = $('<a class="recent-chip"><i class="bi bi-shield-shaded text-primary"></i><span class="label"></span><span class="badge text-bg-secondary bg-opacity-25 text-body"></span><button type="button" class="remove" aria-label="Odstrániť"><i class="bi bi-x"></i></button></a>');
			$a.attr('href', base + '/match-day-season-detail?' + new URLSearchParams({ s: r.season, team: r.teamId }));
			$a.find('.label').text(r.teamName);
			$a.find('.badge').text(r.season);
			$a.find('.remove').on('click', (e) => {
				e.preventDefault(); e.stopPropagation();
				const l = recent(); l.splice(i, 1); MD.store.pref('recent', l); renderRecent();
			});
			$l.append($a);
		});
	}
	$('#recentClear').on('click', () => { MD.store.pref('recent', []); renderRecent(); });
	renderRecent();

	/* ---------- Môj klub: najbližšie zápasy a posledné výsledky ---------- */
	const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
	const logoUrl = (u) => u || ''; // pri chybe sa logo načíta cez server (header.php)
	const fmt = (iso) => { const d = new Date(iso); return isNaN(d) ? '' : d.toLocaleDateString('sk-SK', { timeZone: 'Europe/Bratislava', weekday: 'short', day: 'numeric', month: 'numeric' }) + ' · ' + d.toLocaleTimeString('sk-SK', { timeZone: 'Europe/Bratislava', hour: '2-digit', minute: '2-digit' }); };
	function genUrl(type, m, season) {
		const q = { type, d1name: m.home.name, d2name: m.away.name, d1url: m.home.logo, d2url: m.away.logo, startDate: m.date, competitionName: m.competition, round: m.round,
			matchID: m.id, team1ID: m.home.id, team2ID: m.away.id, myTeam: m.teamId, category: m.cat, season };
		if (m.played) { q.score1 = m.home.score; q.score2 = m.away.score; }
		Object.keys(q).forEach((k) => { if (q[k] === '' || q[k] == null) delete q[k]; });
		return base + '/generator?' + new URLSearchParams(q);
	}
	function row(m, season) {
		const res = m.played ? '<span class="fav-score res-' + esc(m.result) + '">' + esc(m.home.score) + ':' + esc(m.away.score) + '</span>' : '<span class="fav-time">' + esc(fmt(m.date)) + '</span>';
		const btns = m.played
			? '<a class="btn btn-sm btn-primary" href="' + genUrl('result', m, season) + '"><i class="bi bi-image me-1"></i>Výsledok</a><a class="btn btn-sm btn-outline-primary" href="' + genUrl('goals', m, season) + '">Strelci</a>'
			: '<a class="btn btn-sm btn-primary" href="' + genUrl('matchday', m, season) + '"><i class="bi bi-image me-1"></i>Match day</a><a class="btn btn-sm btn-outline-primary" href="' + genUrl('lineup', m, season) + '&side=' + m.side + '">Zostava</a>';
		return '<div class="fav-row"><div class="fav-teams"><span class="fav-cat">' + esc(m.cat) + (m.played ? '' : '') + '</span>' +
			'<a class="fav-match" href="' + base + '/zapas?' + new URLSearchParams({ matchID: m.id, myTeam: m.teamId }) + '">' +
			'<img src="' + esc(logoUrl(m.home.logo)) + '" alt="" loading="lazy">' + esc(m.home.name) + ' <span class="text-muted-2">–</span> ' + esc(m.away.name) + '<img src="' + esc(logoUrl(m.away.logo)) + '" alt="" loading="lazy"></a>' +
			'<div class="small text-muted-2">' + esc(m.competition) + (m.round ? ' · ' + esc(m.round) : '') + '</div></div>' +
			'<div class="fav-side">' + res + '<div class="btn-group">' + btns + '</div></div></div>';
	}
	function renderFav() {
		const f = MD.store.pref('favClub');
		$('#favWrap').prop('hidden', !f);
		if (!f) return;
		const season = $('#season').val() || f.season;
		$('#favName').text(f.name || f.club);
		$('#favLogo').attr('src', logoUrl(f.logo)).prop('hidden', !f.logo);
		$('#favLink').attr('href', base + '/match-day-season-detail?' + new URLSearchParams({ s: season, team: f.club }));
		$('#favWeekend').attr('href', base + '/generator?' + new URLSearchParams({ type: 'weekend', club: f.club, season, d1name: f.name || '', d1url: f.logo || '' }));
		fetch(base + '/api-create?' + new URLSearchParams({ club: f.club, s: season })).then((r) => r.json()).then((j) => MD.create && MD.create.add(j.groups || [])).catch(() => {});
		fetch(base + '/api-club?' + new URLSearchParams({ club: f.club, s: season })).then((r) => r.json()).then((j) => {
			if (j.error) throw new Error(j.error);
			const now = Date.now();
			const next = j.matches.filter((m) => !m.played && new Date(m.date).getTime() > now - 3 * 3600e3).slice(0, 4);
			const last = j.matches.filter((m) => m.played).slice(-4).reverse();
			const col = (title, icon, list, empty) => '<div class="col-lg-6"><h3 class="fav-head"><i class="bi ' + icon + ' me-1"></i>' + title + '</h3>' + (list.length ? list.map((m) => row(m, j.season)).join('') : '<div class="small text-muted-2 py-2">' + empty + '</div>') + '</div>';
			$('#favBody').html(col('Najbližšie zápasy', 'bi-calendar-event', next, 'Žiadne naplánované zápasy.') + col('Posledné výsledky', 'bi-trophy', last, 'Zatiaľ žiadne odohraté zápasy.'));
		}).catch((e) => $('#favBody').html('<div class="col-12 small text-danger"><i class="bi bi-exclamation-triangle me-1"></i>' + esc(e.message || 'Zápasy sa nepodarilo načítať.') + '</div>'));
	}
	$('#favRemove').on('click', () => { MD.store.pref('favClub', null); renderFav(); });
	$('#season').on('change', renderFav);
	renderFav();
});
</script>

<?php add_layout("footer"); ?>
