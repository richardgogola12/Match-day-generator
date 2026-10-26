<?php
	// GET vstupy
	$s    = $_GET['s'] ?? '';    // sezóna "2025/2026"
	$team = $_GET['team'] ?? ''; // ID klubu
	$data2 = ['teams' => []];
	$error = null;

	// získa družstvá klubu
	if (str_starts_with($team, 'issf_club_')) {

		// POZN.: pôvodná logika – URL klubu je zatiaľ napevno (KFC Komárno)
		$url = 'https://sportnet.sme.sk/futbalnet/k/kfc-komarno-futbal/';
		$content = getTeamsContent($url);

		if ($content !== false) {
			$start = strpos($content, '{\"teams\"');
			$end = strrpos($content, ']}') + 2;
			if ($start !== false) {
				$jsonString = str_replace('\"', '"', substr($content, $start, $end - $start));
				$dataExternal = json_decode($jsonString, true);
				if (json_last_error() === JSON_ERROR_NONE) {
					foreach ($dataExternal["teams"] as $key => $value) {
						$data2["teams"][$key] = ["_id" => $value["id"], "displayName" => $value["name"]];
					}
				} else {
					$error = 'Chyba pri dekódovaní JSON: ' . json_last_error_msg();
				}
			} else {
				$error = 'Dáta družstiev sa nenašli.';
			}
		} else {
			$error = 'Obsah s družstvami sa nenašiel.';
		}
	} else {
		try {
			$res = getJsonFromUrl(sprintf(API_TEAMS, rawurlencode($team), rawurlencode($s)));
			if (!empty($res['teams'])) $data2 = $res;
			elseif (!empty($res['error'])) $error = 'Sportnet API vrátilo chybu (HTTP ' . ($res['http_code'] ?? '?') . ').';
		} catch (Exception $e) {
			$error = $e->getMessage();
		}
	}

	/** Odkaz na generátor */
	function gen_link($type, $params) {
		return url('generator') . '?' . http_build_query(['type' => $type] + $params);
	}

	add_layout("header", array("title" => "Zápasy " . $s . " | Match Day"));
?>

<section class="hero hero-sm">
	<div class="container-xxl">
		<a href="<?= url('/') ?>" class="btn btn-sm btn-outline-light rounded-pill mb-3"><i class="bi bi-arrow-left me-1"></i> Nové hľadanie</a>
		<h1 class="mb-2">Zápasy sezóny</h1>
		<div class="d-flex flex-wrap gap-2 align-items-center">
			<span class="hero-eyebrow"><i class="bi bi-calendar3"></i> <?= plain($s) ?></span>
			<span class="hero-eyebrow"><i class="bi bi-people"></i> <?= count($data2['teams']) ?> družstiev</span>
		</div>
	</div>
</section>

<div class="container-xxl overlap">

	<?php if ($error): ?>
		<div class="card-app mb-4"><div class="card-body">
			<div class="alert alert-danger mb-0"><i class="bi bi-exclamation-octagon me-2"></i><?= plain($error) ?></div>
		</div></div>
	<?php elseif (!$data2['teams']): ?>
		<div class="card-app"><div class="empty-state">
			<i class="bi bi-inbox"></i>
			Pre túto sezónu sa nenašli žiadne družstvá.
		</div></div>
	<?php endif; ?>

	<?php if (count($data2['teams']) > 1): ?>
		<div class="d-flex flex-wrap gap-2 mb-3">
			<?php foreach ($data2["teams"] as $value2): ?>
				<a href="#team-<?= plain($value2['_id']) ?>" class="btn btn-sm rounded-pill btn-light border"><?= plain($value2['displayName']) ?></a>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>

	<?php foreach ($data2["teams"] as $value2):
		$team_name = $value2["displayName"];
		$team_id = $value2["_id"];
		$season = str_starts_with($team, 'issf_club_') ? $s : ($value2["season"]["name"] ?? $s);

		try {
			$data = getJsonFromUrl(sprintf(API_MATCHES, rawurlencode($team_id)));
		} catch (Exception $e) {
			$data = ['matches' => []];
		}
		$matches = $data["matches"] ?? [];
		$played = count(array_filter($matches, fn($m) => ($m['matchState'] ?? '') === 'ODOHRATY'));
	?>
		<section class="card-app team-card mb-4" id="team-<?= plain($team_id) ?>">
			<div class="card-header">
				<div>
					<h2 class="team-title"><?= plain($team_name) ?></h2>
					<div class="small text-muted-2"><?= plain($season) ?> · <?= count($matches) ?> zápasov · <?= $played ?> odohraných</div>
				</div>
				<div class="btn-group filter-pills" role="group" aria-label="Filter zápasov">
					<button type="button" class="btn btn-outline-secondary active" data-filter="all">Všetky</button>
					<button type="button" class="btn btn-outline-secondary" data-filter="upcoming">Nadchádzajúce</button>
					<button type="button" class="btn btn-outline-secondary" data-filter="played">Odohraté</button>
				</div>
			</div>

			<?php if (!$matches): ?>
				<div class="empty-state"><i class="bi bi-calendar-x"></i>Žiadne zápasy.</div>
			<?php else: ?>
			<ul class="match-list">
				<?php foreach ($matches as $value):
					$home = $value["homeTeam"];
					$away = $value["awayTeam"];
					$state = $value["matchState"] ?? '';
					$isPlayed = $state === 'ODOHRATY';
					$isBye = $state === 'NEZUCASTNENE_JEDNO_DRUZSTVO';
					$win1 = $isPlayed && !empty($home['isWinner']);
					$win2 = $isPlayed && !empty($away['isWinner']);
					[$dateTxt, $timeTxt] = format_match_date($value["startDate"] ?? '');
					[$stateTxt, $stateColor] = match_state_badge($state);
					$logo1 = $home['logoUrl'] ?? '';
					$logo2 = $away['logoUrl'] ?? '';
					$roundName = $value["round"]["name"] ?? '';

					$params = [
						'd1name' => $home['name'] ?? '',
						'd2name' => $away['name'] ?? '',
						'd1url' => $logo1,
						'd2url' => $logo2,
						'startDate' => $value['startDate'] ?? '',
						'competitionName' => $value['competitionPart']['name'] ?? '',
						'round' => $roundName,
					];
					if ($isPlayed) {
						$params += [
							'score1' => $home['score'] ?? '',
							'score2' => $away['score'] ?? '',
							'matchID' => $value['_id'] ?? '',
							'team1ID' => $home['_id'] ?? '',
							'team2ID' => $away['_id'] ?? '',
						];
					}
				?>
				<li class="match" data-state="<?= $isPlayed ? 'played' : 'upcoming' ?>">
					<div class="match-meta">
						<span class="round"><?= plain(preg_match('/kolo/i', $roundName) ? $roundName : $roundName . '. kolo') ?></span>
						<span><i class="bi bi-calendar3 me-1"></i><?= plain($dateTxt) ?> · <?= plain($timeTxt) ?></span>
						<span class="text-truncate" style="max-width:100%"><?= plain($value["competitionPart"]["name"] ?? '') ?></span>
						<span class="badge rounded-pill text-bg-<?= $stateColor ?> bg-opacity-75"><?= plain($stateTxt) ?></span>
					</div>

					<div class="match-teams">
						<div class="match-team home <?= $win1 ? 'winner' : '' ?>">
							<span class="name"><?= plain($home['name'] ?? '') ?></span>
							<?php if ($logo1): ?><img class="team-logo" src="<?= plain($logo1) ?>" alt="" loading="lazy"><?php else: ?><span class="team-logo empty"><i class="bi bi-shield"></i></span><?php endif; ?>
						</div>
						<?php if ($isPlayed): ?>
							<div class="match-score"><?= plain($home['score'] ?? '') ?> : <?= plain($away['score'] ?? '') ?></div>
						<?php else: ?>
							<div class="match-score upcoming"><?= $isBye ? '–' : plain($timeTxt) ?></div>
						<?php endif; ?>
						<div class="match-team away <?= $win2 ? 'winner' : '' ?>">
							<?php if ($logo2): ?><img class="team-logo" src="<?= plain($logo2) ?>" alt="" loading="lazy"><?php else: ?><span class="team-logo empty"><i class="bi bi-shield"></i></span><?php endif; ?>
							<span class="name"><?= plain($away['name'] ?? '') ?></span>
						</div>
					</div>

					<div class="match-actions">
						<?php if (!$isBye): ?>
							<a class="btn btn-outline-primary" href="<?= plain(gen_link('matchday', $params)) ?>"><i class="bi bi-calendar-event me-1"></i>Match day</a>
							<?php if ($isPlayed): ?>
								<a class="btn btn-outline-primary" href="<?= plain(gen_link('result', $params)) ?>"><i class="bi bi-trophy me-1"></i>Výsledok</a>
								<a class="btn btn-primary" href="<?= plain(gen_link('goals', $params)) ?>"><i class="bi bi-list-ol me-1"></i>Strelci</a>
							<?php endif; ?>
						<?php endif; ?>
					</div>
				</li>
				<?php endforeach; ?>
			</ul>
			<div class="empty-state filter-empty" hidden><i class="bi bi-funnel"></i>Žiadne zápasy pre zvolený filter.</div>
			<?php endif; ?>
		</section>
	<?php endforeach; ?>
</div>

<script>
$(function () {
	$('.filter-pills').on('click', '[data-filter]', function () {
		const $btn = $(this), f = $btn.data('filter');
		const $card = $btn.closest('.team-card');
		$btn.addClass('active').siblings().removeClass('active');
		let visible = 0;
		$card.find('.match').each(function () {
			const show = f === 'all' || this.dataset.state === f;
			this.hidden = !show;
			if (show) visible++;
		});
		$card.find('.filter-empty').prop('hidden', visible > 0);
	});
});
</script>

<?php add_layout("footer"); ?>
