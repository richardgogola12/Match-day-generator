<?php
	$type = $_GET['type'] ?? 'matchday';
	if (!in_array($type, ['matchday', 'result', 'goals'], true)) $type = 'matchday';

	$match = [
		'd1name'      => $_GET['d1name'] ?? '',
		'd2name'      => $_GET['d2name'] ?? '',
		'd1url'       => $_GET['d1url'] ?? '',
		'd2url'       => $_GET['d2url'] ?? '',
		'startDate'   => $_GET['startDate'] ?? '',
		'competition' => $_GET['competitionName'] ?? '',
		'round'       => $_GET['round'] ?? '',
		'score1'      => $_GET['score1'] ?? '',
		'score2'      => $_GET['score2'] ?? '',
		'matchID'     => $_GET['matchID'] ?? '',
		'team1ID'     => $_GET['team1ID'] ?? '',
		'team2ID'     => $_GET['team2ID'] ?? '',
	];
	$played = $match['score1'] !== '' && $match['score2'] !== '';
	[$dateTxt, $timeTxt] = format_match_date($match['startDate']);

	add_layout("header", array(
		"title"   => "Generátor – " . $match['d1name'] . " vs " . $match['d2name'],
		"scripts" => ["js/renderer.js", "js/generator.js"],
	));
?>

<section class="hero hero-sm">
	<div class="container-xxl">
		<button type="button" onclick="history.length > 1 ? history.back() : location.href='<?= url('/') ?>'" class="btn btn-sm btn-outline-light rounded-pill mb-3"><i class="bi bi-arrow-left me-1"></i> Späť na zápasy</button>
		<h1 class="mb-2"><?= plain($match['d1name']) ?> <span class="accent">vs</span> <?= plain($match['d2name']) ?></h1>
		<div class="d-flex flex-wrap gap-2">
			<?php if ($dateTxt): ?><span class="hero-eyebrow"><i class="bi bi-calendar3"></i> <?= plain($dateTxt) ?> · <?= plain($timeTxt) ?></span><?php endif; ?>
			<?php if ($match['competition']): ?><span class="hero-eyebrow"><i class="bi bi-trophy"></i> <?= plain($match['competition']) ?></span><?php endif; ?>
		</div>
	</div>
</section>

<div class="container-xxl overlap">
	<div class="row g-4">
		<div class="col-lg-7 col-xl-8">
			<div class="card-app">
				<div class="card-body">
					<div class="preview-frame">
						<canvas id="out" width="1000" height="1000" aria-label="Náhľad obrázka"></canvas>
						<div class="preview-loading" id="loading">
							<div class="text-center">
								<div class="spinner-border text-primary mb-2" role="status"></div>
								<div class="small fw-semibold" id="loadingText">Pripravujem obrázok…</div>
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>

		<div class="col-lg-5 col-xl-4">
			<div class="card-app mb-4">
				<div class="card-body">
					<h2 class="h6 section-title text-uppercase text-muted-2 mb-3">Typ obrázka</h2>
					<div class="row g-2 template-switch" id="templateSwitch">
						<div class="col-4"><button type="button" class="btn btn-outline-primary w-100 flex-column py-2" data-type="matchday"><i class="bi bi-calendar-event fs-5"></i><span class="small">Match day</span></button></div>
						<div class="col-4"><button type="button" class="btn btn-outline-primary w-100 flex-column py-2" data-type="result" <?= $played ? '' : 'disabled' ?>><i class="bi bi-trophy fs-5"></i><span class="small">Výsledok</span></button></div>
						<div class="col-4"><button type="button" class="btn btn-outline-primary w-100 flex-column py-2" data-type="goals" <?= $played && $match['matchID'] ? '' : 'disabled' ?>><i class="bi bi-list-ol fs-5"></i><span class="small">Strelci</span></button></div>
					</div>
					<?php if (!$played): ?>
						<div class="form-text mt-2"><i class="bi bi-info-circle me-1"></i>Výsledok a strelci budú dostupní po odohratí zápasu.</div>
					<?php endif; ?>

					<div class="d-grid gap-2 mt-4">
						<button type="button" class="btn btn-primary btn-lg" id="btnDownload" disabled><i class="bi bi-download me-2"></i>Stiahnuť PNG</button>
						<div class="d-flex gap-2">
							<button type="button" class="btn btn-outline-secondary flex-fill" id="btnCopy" disabled><i class="bi bi-clipboard me-1"></i>Kopírovať</button>
							<button type="button" class="btn btn-outline-secondary flex-fill" id="btnShare" hidden><i class="bi bi-share me-1"></i>Zdieľať</button>
						</div>
						<a class="btn btn-outline-secondary" id="btnEdit" href="<?= url('nastavenia') ?>"><i class="bi bi-sliders me-1"></i>Upraviť rozloženie šablóny</a>
					</div>

					<div class="server-save mt-4" id="serverSave">
						<div class="d-flex align-items-start gap-2">
							<span class="server-save-icon" id="srvIcon"><i class="bi bi-hdd-network"></i></span>
							<div class="flex-fill min-w-0">
								<div class="fw-semibold small" id="srvTitle">Uloženie na server</div>
								<div class="small text-muted-2 text-truncate" id="srvText">Čakám na vygenerovanie…</div>
							</div>
						</div>
						<div class="d-flex flex-wrap gap-2 mt-2" id="srvActions" hidden>
							<a class="btn btn-sm btn-outline-secondary" id="srvOpen" target="_blank" rel="noopener"><i class="bi bi-box-arrow-up-right me-1"></i>Otvoriť</a>
							<button type="button" class="btn btn-sm btn-outline-secondary" id="srvCopy"><i class="bi bi-link-45deg me-1"></i>Kopírovať odkaz</button>
							<a class="btn btn-sm btn-outline-secondary" href="<?= url('galeria') ?>"><i class="bi bi-images me-1"></i>Galéria</a>
						</div>
						<div class="d-flex align-items-center justify-content-between gap-2 mt-3 pt-3 border-top">
							<div class="form-check form-switch mb-0">
								<input class="form-check-input" type="checkbox" role="switch" id="autoSave" checked>
								<label class="form-check-label small" for="autoSave">Automaticky ukladať</label>
							</div>
							<button type="button" class="btn btn-sm btn-primary" id="btnSaveServer" disabled><i class="bi bi-cloud-upload me-1"></i>Uložiť</button>
						</div>
					</div>
				</div>
			</div>

			<div class="card-app">
				<div class="card-body">
					<h2 class="h6 section-title text-uppercase text-muted-2 mb-3">Údaje o zápase</h2>
					<dl class="kv mb-0">
						<dt>Domáci</dt><dd><?= plain($match['d1name']) ?></dd>
						<dt>Hostia</dt><dd><?= plain($match['d2name']) ?></dd>
						<?php if ($played): ?><dt>Výsledok</dt><dd><?= plain($match['score1']) ?> : <?= plain($match['score2']) ?></dd><?php endif; ?>
						<dt>Dátum</dt><dd><?= plain($dateTxt) ?> <?= plain($timeTxt) ?></dd>
						<dt>Súťaž</dt><dd><?= plain($match['competition']) ?></dd>
						<dt>Kolo</dt><dd><?= plain($match['round']) ?></dd>
					</dl>
					<div id="goalsInfo" class="mt-3" hidden>
						<h3 class="h6 section-title text-uppercase text-muted-2 mt-4 mb-2">Strelci</h3>
						<div class="row g-2 small" id="goalsCols"></div>
					</div>
				</div>
			</div>
		</div>
	</div>
</div>

<script>
	window.MD_MATCH = <?= json_encode($match, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>;
	window.MD_TYPE = <?= json_encode($type) ?>;
</script>

<?php add_layout("footer"); ?>
