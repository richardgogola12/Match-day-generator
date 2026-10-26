<?php
	try {
		$data = getJsonFromUrl(API_SEASONS);
	} catch (Exception $e) {
		$data = ['error' => true];
	}
	$seasons = !empty($data['seasons']) ? array_reverse($data['seasons']) : [];

	add_layout("header", array("title" => "Match day – generátor"));
?>

<section class="hero">
	<div class="container-xxl">
		<span class="hero-eyebrow mb-3"><i class="bi bi-lightning-charge-fill text-warning"></i> Grafiky k zápasom za pár sekúnd</span>
		<h1 class="mb-3">Match <span class="accent">Day</span> generátor</h1>
		<p class="lead mb-0">Vyberte sezónu a družstvo, nájdite zápas a stiahnite si hotový obrázok – pozvánku, výsledok aj strelcov gólov.</p>
	</div>
</section>

<div class="container-xxl overlap">
	<div class="row g-4">
		<div class="col-lg-7 col-xl-8">
			<div class="card-app">
				<div class="card-body">
					<h2 class="h5 section-title mb-4"><i class="bi bi-search me-2 text-primary"></i>Vyhľadať zápasy</h2>

					<?php if (!$seasons): ?>
						<div class="alert alert-warning d-flex gap-2 align-items-start">
							<i class="bi bi-exclamation-triangle-fill"></i>
							<div>Nepodarilo sa načítať zoznam sezón zo Sportnet API. Skúste stránku obnoviť o chvíľu.</div>
						</div>
					<?php endif; ?>

					<form id="searchForm" class="row g-3" novalidate>
						<div class="col-md-5">
							<label for="season" class="form-label fw-semibold">Sezóna</label>
							<select class="form-select form-select-lg" id="season" required>
								<?php foreach ($seasons as $i => $value): ?>
									<option value="<?= plain($value["name"]) ?>" <?= $i === 0 ? 'selected' : '' ?>><?= plain($value["name"]) ?></option>
								<?php endforeach; ?>
							</select>
						</div>
						<div class="col-md-7">
							<label for="teamName" class="form-label fw-semibold">Klub / družstvo</label>
							<select class="js-data form-select" id="teamName" style="width:100%" required></select>
							<div class="form-text">Začnite písať aspoň 3 znaky názvu klubu.</div>
						</div>
						<div class="col-12 d-grid d-sm-flex gap-2">
							<button id="btnSearch" type="submit" class="btn btn-primary btn-lg px-4">
								<i class="bi bi-search me-1"></i> Zobraziť zápasy
							</button>
							<a href="<?= url('nastavenia') ?>" class="btn btn-outline-secondary btn-lg px-4">
								<i class="bi bi-sliders me-1"></i> Upraviť šablóny
							</a>
						</div>
					</form>

					<div id="recentWrap" class="mt-4 pt-4 border-top" hidden>
						<div class="d-flex justify-content-between align-items-center mb-2">
							<h3 class="h6 mb-0 fw-semibold text-muted-2"><i class="bi bi-clock-history me-1"></i> Naposledy hľadané</h3>
							<button type="button" class="btn btn-link btn-sm text-decoration-none p-0" id="recentClear">Vymazať</button>
						</div>
						<div id="recentList" class="d-flex flex-wrap gap-2"></div>
					</div>
				</div>
			</div>
		</div>

		<div class="col-lg-5 col-xl-4">
			<div class="card-app h-100">
				<div class="card-body d-flex flex-column gap-4">
					<h2 class="h5 section-title mb-0">Čo vygenerujete</h2>
					<div class="feature">
						<div class="feature-icon"><i class="bi bi-calendar-event"></i></div>
						<div><div class="fw-semibold">Match day</div><div class="small text-muted-2">Pozvánka na zápas s logami, dátumom a časom výkopu.</div></div>
					</div>
					<div class="feature">
						<div class="feature-icon"><i class="bi bi-trophy"></i></div>
						<div><div class="fw-semibold">Výsledok</div><div class="small text-muted-2">Konečné skóre odohraného zápasu.</div></div>
					</div>
					<div class="feature">
						<div class="feature-icon"><i class="bi bi-list-ol"></i></div>
						<div><div class="fw-semibold">Strelci</div><div class="small text-muted-2">Skóre a zoznam strelcov oboch tímov s minútami.</div></div>
					</div>
					<div class="mt-auto p-3 rounded-3" style="background:var(--md-surface-2)">
						<div class="small"><i class="bi bi-palette me-1 text-primary"></i> Pozadia, rozloženie a písmo si nastavíte v <a href="<?= url('nastavenia') ?>">editore šablón</a> ťahaním myšou.</div>
					</div>
				</div>
			</div>
		</div>
	</div>
</div>

<script>
$(function () {
	const MD = window.MD;
	const RECENT_MAX = 6;

	$('#teamName').select2({
		placeholder: 'Vyberte si družstvo',
		allowClear: true,
		minimumInputLength: 3,
		width: '100%',
		theme: 'bootstrap-5',
		selectionCssClass: 'select2--large',
		dropdownCssClass: 'select2--large',
		language: {
			inputTooShort: () => 'Zadajte aspoň 3 znaky…',
			searching: () => 'Hľadám…',
			noResults: () => 'Nič sa nenašlo',
			errorLoading: () => 'Výsledky sa nepodarilo načítať',
		},
		ajax: {
			url: 'https://api.sportnet.online/v1/ppo/futbalsfz.sk/related-ppos?&ppoType=club&limit=20&offset=0',
			dataType: 'json',
			delay: 250,
			data: (params) => ({ q: params.term }),
			processResults: (data) => ({
				results: (data.items || []).map((item) => ({ id: item._id.split('.futbalnet.sk')[0], text: item.name })),
			}),
		},
	});

	function go(season, teamId) {
		window.location.href = 'match-day-season-detail?s=' + encodeURIComponent(season) + '&team=' + encodeURIComponent(teamId);
	}

	/* Posledné hľadania – iba v prehliadači */
	function recent() { return MD.store.pref('recent') || []; }
	function renderRecent() {
		const list = recent();
		$('#recentWrap').prop('hidden', !list.length);
		const $l = $('#recentList').empty();
		list.forEach((r, i) => {
			const $a = $('<a class="recent-chip" href="#"><i class="bi bi-shield-shaded text-primary"></i><span class="label"></span><span class="badge text-bg-secondary bg-opacity-25 text-body"></span><button type="button" class="remove" aria-label="Odstrániť"><i class="bi bi-x"></i></button></a>');
			$a.find('.label').text(r.teamName);
			$a.find('.badge').text(r.season);
			$a.on('click', (e) => { e.preventDefault(); go(r.season, r.teamId); });
			$a.find('.remove').on('click', (e) => {
				e.preventDefault(); e.stopPropagation();
				const l = recent(); l.splice(i, 1); MD.store.pref('recent', l); renderRecent();
			});
			$l.append($a);
		});
	}
	$('#recentClear').on('click', () => { MD.store.pref('recent', []); renderRecent(); });
	renderRecent();

	$('#searchForm').on('submit', function (e) {
		e.preventDefault();
		const season = $('#season').val();
		const sel = $('#teamName').select2('data')[0];
		if (!season || !sel || !sel.id) {
			$('#teamName').select2('open');
			return;
		}
		const list = recent().filter((r) => !(r.teamId === sel.id && r.season === season));
		list.unshift({ teamId: sel.id, teamName: sel.text, season });
		MD.store.pref('recent', list.slice(0, RECENT_MAX));
		go(season, sel.id);
	});
});
</script>

<?php add_layout("footer"); ?>
