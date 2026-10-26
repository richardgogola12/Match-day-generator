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
});
</script>

<?php add_layout("footer"); ?>
