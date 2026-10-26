<?php
	$GROUPS = [
		'match'  => ['matchday' => ['bi-calendar-event', 'Match day'], 'lineup' => ['bi-person-lines-fill', 'Zostava'], 'result' => ['bi-trophy', 'Výsledok'], 'goals' => ['bi-list-ol', 'Strelci'], 'report' => ['bi-file-text', 'Súhrn']],
		'team'   => ['squad' => ['bi-people', 'Súpiska'], 'table' => ['bi-list-ol', 'Tabuľka'], 'round' => ['bi-calendar-week', 'Kolo'], 'stats' => ['bi-bar-chart-line', 'Štatistiky']],
		'player' => ['player' => ['bi-person-vcard', 'Profil'], 'motm' => ['bi-award', 'Hráč zápasu'], 'milestone' => ['bi-trophy', 'Jubileum']],
	];
	$type = $_GET['type'] ?? 'matchday';
	$mode = 'match';
	foreach ($GROUPS as $g => $types) if (isset($types[$type])) $mode = $g;
	if (!isset($GROUPS[$mode][$type])) $type = 'matchday';

	$g = fn($k) => (string)($_GET[$k] ?? '');
	$match = [
		'd1name' => $g('d1name'), 'd2name' => $g('d2name'), 'd1url' => $g('d1url'), 'd2url' => $g('d2url'),
		'startDate' => $g('startDate'), 'competition' => $g('competitionName'), 'round' => $g('round'),
		'score1' => $g('score1'), 'score2' => $g('score2'), 'matchID' => $g('matchID'),
		'team1ID' => $g('team1ID'), 'team2ID' => $g('team2ID'), 'myTeam' => $g('myTeam'),
		// družstvo / hráč
		'team' => $g('team'), 'category' => $g('category'), 'season' => $g('season'),
		'player' => $g('player'), 'matchRef' => $g('matchRef'), 'roundNo' => $g('roundNo'),
		'mvalue' => $g('mvalue'), 'mkind' => $g('mkind'),
	];
	$played = $match['score1'] !== '' && $match['score2'] !== '';
	[$dateTxt, $timeTxt] = format_match_date($match['startDate']);

	$enabled = [
		'lineup' => $match['matchID'] !== '', 'result' => $played,
		'goals' => $played && $match['matchID'] !== '', 'report' => $played && $match['matchID'] !== '',
	];
	$teamListUrl = $match['team'] ? url('druzstvo') . '?' . http_build_query(['team' => $match['team'], 's' => $match['season'], 'cat' => $match['category']]) : '';

	add_layout("header", array(
		"title"   => $mode === 'match' ? "Generátor – " . $match['d1name'] . " vs " . $match['d2name'] : "Generátor – " . $match['d1name'],
		"scripts" => ["js/renderer.js", "js/generator.js"],
	));
?>

<section class="hero hero-sm">
	<div class="container-xxl">
		<button type="button" onclick="history.length > 1 ? history.back() : location.href='<?= url('/') ?>'" class="btn btn-sm btn-outline-light rounded-pill mb-3"><i class="bi bi-arrow-left me-1"></i> Späť</button>
		<?php if ($mode === 'match'): ?>
			<h1 class="mb-2"><?= plain($match['d1name']) ?> <span class="accent">vs</span> <?= plain($match['d2name']) ?></h1>
			<div class="d-flex flex-wrap gap-2">
				<?php if ($dateTxt): ?><span class="hero-eyebrow"><i class="bi bi-calendar3"></i> <?= plain($dateTxt) ?> · <?= plain($timeTxt) ?></span><?php endif; ?>
				<?php if ($match['competition']): ?><span class="hero-eyebrow"><i class="bi bi-trophy"></i> <?= plain($match['competition']) ?></span><?php endif; ?>
			</div>
		<?php else: ?>
			<h1 class="mb-2"><span id="heroPlayer"><?= $mode === 'player' ? '' : 'Družstvo ' ?></span><span class="accent"><?= plain($match['d1name']) ?></span></h1>
			<div class="d-flex flex-wrap gap-2">
				<?php if ($match['category']): ?><span class="hero-eyebrow"><i class="bi bi-people"></i> <?= plain($match['category']) ?></span><?php endif; ?>
				<?php if ($match['season']): ?><span class="hero-eyebrow"><i class="bi bi-calendar3"></i> <?= plain($match['season']) ?></span><?php endif; ?>
				<?php if ($match['competition']): ?><span class="hero-eyebrow"><i class="bi bi-trophy"></i> <?= plain($match['competition']) ?></span><?php endif; ?>
				<?php if ($teamListUrl): ?><a class="hero-eyebrow text-decoration-none text-white" href="<?= plain($teamListUrl) ?>"><i class="bi bi-bar-chart-line"></i> Prehľad družstva</a><?php endif; ?>
			</div>
		<?php endif; ?>
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
						<?php $n = count($GROUPS[$mode]); foreach ($GROUPS[$mode] as $t => [$icon, $label]): ?>
							<div class="<?= $n >= 5 ? 'col-4' : ($n === 3 ? 'col-4' : 'col-6 col-sm-3 col-lg-6 col-xxl-3') ?>"><button type="button" class="btn btn-outline-primary w-100 flex-column py-2" data-type="<?= $t ?>" <?= ($enabled[$t] ?? true) ? '' : 'disabled' ?>><i class="bi <?= $icon ?> fs-5"></i><span class="small"><?= $label ?></span></button></div>
						<?php endforeach; ?>
					</div>
					<?php if ($mode === 'match' && !$played): ?>
						<div class="form-text mt-2"><i class="bi bi-info-circle me-1"></i>Výsledok, strelci a súhrn budú dostupné po odohratí zápasu.</div>
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

			<?php if ($mode === 'player'): ?>
			<!-- Hráč -->
			<div class="card-app mb-4" id="playerCard">
				<div class="card-body">
					<h2 class="h6 section-title text-uppercase text-muted-2 mb-3">Hráč</h2>
					<label class="form-label small fw-semibold" for="selPlayer">Vybraný hráč</label>
					<select class="form-select mb-3" id="selPlayer"><option>Načítavam…</option></select>
					<div class="d-flex align-items-center gap-3 mb-3">
						<div class="player-photo small-photo" id="genPhotoBox"><span class="player-nr" id="genPhotoNr">?</span><img id="genPhotoImg" alt="" hidden></div>
						<div class="d-grid gap-2 flex-fill">
							<label class="btn btn-sm btn-outline-primary mb-0"><i class="bi bi-upload me-1"></i>Nahrať fotku<input type="file" accept="image/*" id="genPhotoFile" hidden></label>
							<button type="button" class="btn btn-sm btn-outline-danger" id="genPhotoRemove" hidden><i class="bi bi-trash3 me-1"></i>Odstrániť fotku</button>
						</div>
					</div>
					<div class="small text-muted-2 mb-3">Fotka sa uloží iba v tomto prehliadači. Najlepšie vyzerá PNG s priehľadným pozadím.</div>

					<div id="motmBox" hidden>
						<label class="form-label small fw-semibold" for="selMatch">Zápas</label>
						<select class="form-select mb-3" id="selMatch"></select>
					</div>
					<div id="milestoneBox" hidden>
						<div class="row g-2 mb-2">
							<div class="col-4">
								<label class="form-label small fw-semibold" for="msValue">Číslo</label>
								<input type="number" class="form-control" id="msValue" min="1" max="9999">
							</div>
							<div class="col-8">
								<label class="form-label small fw-semibold" for="msKind">Jubileum</label>
								<select class="form-select" id="msKind">
									<option value="apps">zápas za klub</option>
									<option value="goals">gól za klub</option>
									<option value="custom">vlastný text…</option>
								</select>
							</div>
						</div>
						<input type="text" class="form-control mb-2" id="msLabel" placeholder="Text, napr. zápas v drese FK…" hidden>
						<div class="form-text">V tejto sezóne: <span id="msHint"></span>. Pri celkovom počte za kariéru zadajte číslo ručne.</div>
					</div>
					<div id="playerStats" class="small text-muted-2 mt-2"></div>
				</div>
			</div>
			<?php endif; ?>

			<?php if ($mode === 'team'): ?>
			<!-- Kolo -->
			<div class="card-app mb-4" id="roundCard" hidden>
				<div class="card-body">
					<h2 class="h6 section-title text-uppercase text-muted-2 mb-3">Kolo</h2>
					<select class="form-select" id="selRound"><option>Načítavam…</option></select>
					<div class="form-check form-switch mt-3 mb-0">
						<input class="form-check-input" type="checkbox" role="switch" id="roundOwnFirst" checked>
						<label class="form-check-label small" for="roundOwnFirst">Zápas nášho družstva ako prvý</label>
					</div>
				</div>
			</div>
			<div class="card-app mb-4" id="teamInfoCard">
				<div class="card-body small" id="teamInfo"><span class="text-muted-2">Načítavam údaje družstva…</span></div>
			</div>
			<?php endif; ?>

			<!-- Hráči (zostava / súpiska) -->
			<div class="card-app mb-4" id="playersCard" hidden>
				<div class="card-body">
					<div class="d-flex justify-content-between align-items-center mb-3">
						<h2 class="h6 section-title text-uppercase text-muted-2 mb-0" id="playersTitle">Zostava</h2>
						<span class="small text-muted-2" id="playersSource"></span>
					</div>
					<?php if ($mode === 'match'): ?>
						<div class="btn-group w-100 mb-3" role="group" aria-label="Zostava družstva" id="sideSwitch">
							<button type="button" class="btn btn-outline-secondary text-truncate" data-side="home"><?= plain($match['d1name'] ?: 'Domáci') ?></button>
							<button type="button" class="btn btn-outline-secondary text-truncate" data-side="away"><?= plain($match['d2name'] ?: 'Hostia') ?></button>
						</div>
					<?php endif; ?>
					<div id="playersStatus" class="small mb-3"></div>
					<div id="playersPreview" class="players-preview mb-3"></div>
					<div class="d-flex flex-wrap gap-2">
						<button type="button" class="btn btn-outline-primary flex-fill" id="btnEditPlayers"><i class="bi bi-pencil-square me-1"></i>Upraviť hráčov</button>
						<button type="button" class="btn btn-outline-secondary" id="btnReloadPlayers" title="Načítať znova zo Sportnetu"><i class="bi bi-arrow-clockwise"></i></button>
					</div>
				</div>
			</div>

			<?php if ($mode === 'match'): ?>
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
					<?php if ($match['matchID']): ?>
						<a class="btn btn-sm btn-outline-secondary mt-3" href="<?= plain(url('zapas') . '?' . http_build_query($_GET)) ?>"><i class="bi bi-file-text me-1"></i>Celý zápis o stretnutí</a>
					<?php endif; ?>
				</div>
			</div>
			<?php endif; ?>
		</div>
	</div>
</div>

<!-- Úprava hráčov -->
<div class="modal fade" id="playersModal" tabindex="-1" aria-labelledby="pmTitle" aria-hidden="true">
	<div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-lg modal-fullscreen-sm-down">
		<div class="modal-content">
			<div class="modal-header">
				<div>
					<h5 class="modal-title" id="pmTitle">Upraviť hráčov</h5>
					<div class="small text-muted-2" id="pmSub"></div>
				</div>
				<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Zavrieť"></button>
			</div>
			<div class="modal-body">
				<p class="small text-muted-2 mb-3">Úpravy sa uložia iba v tomto prehliadači a použijú sa pri každom ďalšom vygenerovaní. Údaje na Sportnete sa nemenia.</p>
				<div class="table-responsive">
					<table class="table table-sm align-middle players-table mb-2">
						<thead><tr><th style="width:4.5rem">Číslo</th><th>Meno</th><th style="width:9.5rem" id="pmRoleHead">Zaradenie</th><th class="text-center" title="Kapitán">C</th><th class="text-center" title="Brankár">B</th><th></th></tr></thead>
						<tbody id="pmRows"></tbody>
					</table>
				</div>
				<button type="button" class="btn btn-sm btn-outline-primary" id="pmAdd"><i class="bi bi-plus-lg me-1"></i>Pridať hráča</button>
				<div class="row g-2 mt-3">
					<div class="col-sm-8">
						<label class="form-label small fw-semibold" for="pmCoach">Tréner</label>
						<input type="text" class="form-control" id="pmCoach" placeholder="Meno trénera">
					</div>
				</div>
			</div>
			<div class="modal-footer justify-content-between">
				<button type="button" class="btn btn-outline-danger" id="pmReset"><i class="bi bi-arrow-counterclockwise me-1"></i>Vrátiť údaje zo Sportnetu</button>
				<div class="d-flex gap-2">
					<button type="button" class="btn btn-light" data-bs-dismiss="modal">Zrušiť</button>
					<button type="button" class="btn btn-primary" id="pmSave"><i class="bi bi-check2 me-1"></i>Použiť</button>
				</div>
			</div>
		</div>
	</div>
</div>

<script>
	window.MD_MATCH = <?= json_encode($match, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>;
	window.MD_TYPE = <?= json_encode($type) ?>;
	window.MD_MODE = <?= json_encode($mode) ?>;
</script>

<?php add_layout("footer"); ?>
