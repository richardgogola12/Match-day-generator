<?php
	// GET vstupy
	$s    = (string)($_GET['s'] ?? '');    // sezóna "2025/2026" (prázdna = aktuálna)
	$team = (string)($_GET['team'] ?? ''); // ID klubu
	if (!preg_match('#^\d{4}/\d{4}$#', $s)) $s = md_default_season();
	$data2 = ['teams' => []];
	$error = null;

	// družstvá klubu (appSpace: "klub.futbalnet.sk" alebo "issf_club_…")
	if (!preg_match('/^[A-Za-z0-9_\-.]{2,120}$/', $team)) show_404();
	$appSpace = str_starts_with($team, 'issf_') || str_contains($team, '.') ? $team : $team . '.futbalnet.sk';
	$res = sn_fetch_cached(sprintf(API_CLUB_TEAMS, rawurlencode($appSpace), rawurlencode($s)), 1800);
	if (!empty($res['teams'])) $data2 = $res;
	elseif (!empty($res['error'])) $error = 'Družstvá klubu sa nepodarilo načítať zo Sportnetu. Skúste to o chvíľu.';

	/** Odkaz na generátor */
	function gen_link($type, $params) {
		return url('generator') . '?' . http_build_query(['type' => $type] + $params);
	}

	// zápasy všetkých družstiev naraz (paralelne)
	$teams = array_values($data2['teams']);
	$lists = sn_fetch_many(array_map(fn($t) => sprintf(API_MATCHES, rawurlencode((string)$t['_id'])), $teams), 900);
	$clubName = (string)($teams[0]['name'] ?? '');
	$clubLogo = '';
	foreach ($teams as $i => &$t) {
		$t['matches'] = (array)($lists[$i]['matches'] ?? []);
		[$t['ownId'], $t['ownName'], $t['ownLogo']] = $t['matches'] ? sn_own_team($t['matches'], (string)$t['_id']) : ['', '', ''];
		if (!$clubLogo && $t['ownLogo']) $clubLogo = $t['ownLogo'];
		if ($clubName === '' && $t['ownName']) $clubName = $t['ownName'];
	}
	unset($t);
	$sel = (string)($_GET['t'] ?? '');
	if (!array_filter($teams, fn($t) => $t['_id'] === $sel)) {
		// predvolene družstvo s najbližším zápasom (dospelí majú prednosť)
		$sel = (string)($teams[0]['_id'] ?? '');
		foreach ($teams as $t) if ($t['matches'] && preg_match('/dospel|muži|a-tím/iu', $t['displayName'] ?? '')) { $sel = $t['_id']; break; }
	}
	$seasonsAll = array_reverse(array_column((array)(sn_fetch_cached(API_SEASONS, 24 * 3600)['seasons'] ?? []), 'name'));
	$seasonsAll = array_slice($seasonsAll, 0, 6);
	if ($s !== '' && !in_array($s, $seasonsAll, true)) array_unshift($seasonsAll, $s);

	/** Parametre zápasu pre generátor */
	$matchParams = function ($m, $ownId) {
		$p = [
			'd1name' => $m['homeTeam']['name'] ?? '', 'd2name' => $m['awayTeam']['name'] ?? '',
			'd1url' => $m['homeTeam']['logoUrl'] ?? '', 'd2url' => $m['awayTeam']['logoUrl'] ?? '',
			'startDate' => $m['startDate'] ?? '', 'competitionName' => $m['competitionPart']['name'] ?? '',
			'round' => $m['round']['name'] ?? '', 'matchID' => $m['_id'] ?? '',
			'team1ID' => $m['homeTeam']['_id'] ?? '', 'team2ID' => $m['awayTeam']['_id'] ?? '', 'myTeam' => $ownId,
		];
		if (($m['matchState'] ?? '') === 'ODOHRATY') $p += ['score1' => $m['homeTeam']['score'] ?? '', 'score2' => $m['awayTeam']['score'] ?? ''];
		return $p;
	};

	md_create_add(md_create_club(sn_club_slug($team), $clubName, $clubLogo, $s));
	add_layout("header", array("title" => ($clubName ?: 'Zápasy') . ' ' . $s . " | Match Day"));
?>

<section class="hero hero-sm">
	<div class="container-xxl">
		<div class="club-head">
			<?php if ($clubLogo): ?><img class="club-logo" src="<?= plain($clubLogo) ?>" alt=""><?php else: ?><span class="club-logo empty"><i class="bi bi-shield"></i></span><?php endif; ?>
			<div class="min-w-0 flex-grow-1">
				<h1 class="mb-2"><?= plain($clubName ?: 'Zápasy klubu') ?></h1>
				<form class="d-flex flex-wrap gap-2 align-items-center" method="get" action="<?= url('match-day-season-detail') ?>">
					<input type="hidden" name="team" value="<?= plain($team) ?>">
					<label class="visually-hidden" for="seasonSel">Sezóna</label>
					<select class="form-select form-select-sm season-pill" id="seasonSel" name="s" onchange="this.form.submit()">
						<?php foreach ($seasonsAll as $sn): ?><option value="<?= plain($sn) ?>" <?= $sn === $s ? 'selected' : '' ?>><?= plain($sn) ?></option><?php endforeach; ?>
					</select>
					<span class="hero-eyebrow"><i class="bi bi-people"></i> <?= count($teams) ?> <?= count($teams) === 1 ? 'družstvo' : (count($teams) < 5 ? 'družstvá' : 'družstiev') ?></span>
					<?php if ($teams): ?><a class="btn btn-sm btn-warning rounded-pill fw-semibold" href="<?= plain(md_gen_link('weekend', ['club' => sn_club_slug($team), 'season' => $s, 'd1name' => $clubName, 'd1url' => $clubLogo])) ?>" title="Obrázok so zápasmi všetkých družstiev klubu v týždni"><i class="bi bi-calendar2-week me-1"></i>Víkend klubu</a><?php endif; ?>
					<button type="button" class="btn btn-sm btn-outline-light rounded-pill fav-btn" id="favBtn" data-club="<?= plain(sn_club_slug($team)) ?>" data-name="<?= plain($clubName) ?>" data-logo="<?= plain($clubLogo) ?>" data-season="<?= plain($s) ?>" aria-pressed="false" title="Zobraziť najbližšie zápasy klubu hneď na úvodnej stránke"><i class="bi bi-star me-1"></i><span>Môj klub</span></button>
				</form>
			</div>
		</div>
	</div>
</section>

<div class="container-xxl overlap">

	<?php if ($error): ?>
		<div class="card-app mb-4"><div class="card-body">
			<div class="alert alert-danger mb-0"><i class="bi bi-exclamation-octagon me-2"></i><?= plain($error) ?></div>
		</div></div>
	<?php elseif (!$teams): ?>
		<div class="card-app"><div class="empty-state">
			<i class="bi bi-inbox"></i>
			Pre sezónu <?= plain($s) ?> sa nenašli žiadne družstvá. Skúste inú sezónu.
		</div></div>
	<?php endif; ?>

	<?php if (count($teams) > 1): ?>
		<nav class="club-tabs mb-3" aria-label="Družstvá klubu">
			<?php foreach ($teams as $t): ?>
				<a href="?<?= plain(http_build_query(['s' => $s, 'team' => $team, 't' => $t['_id']])) ?>" data-team-tab="<?= plain($t['_id']) ?>" class="club-tab<?= $t['_id'] === $sel ? ' active' : '' ?>"<?= $t['_id'] === $sel ? ' aria-current="true"' : '' ?>>
					<?= plain($t['displayName'] ?? '') ?> <span class="count"><?= count($t['matches']) ?></span>
				</a>
			<?php endforeach; ?>
		</nav>
	<?php endif; ?>

	<?php foreach ($teams as $tm):
		$team_name = $tm["displayName"] ?? '';
		$team_id = (string)$tm["_id"];
		$season = $tm["season"]["name"] ?? $s;
		$matches = $tm['matches'];
		$ownId = $tm['ownId'];
		$played = array_values(array_filter($matches, fn($m) => ($m['matchState'] ?? '') === 'ODOHRATY'));
		$upcoming = array_values(array_filter($matches, fn($m) => ($m['matchState'] ?? '') !== 'ODOHRATY' && ($m['matchState'] ?? '') !== 'NEZUCASTNENE_JEDNO_DRUZSTVO' && strtotime($m['startDate'] ?? '') > time() - 3 * 3600));
		$squadLink = gen_link('squad', ['team' => $team_id, 'd1name' => $tm['ownName'] ?: $team_name, 'd1url' => $tm['ownLogo'], 'category' => $team_name,
			'season' => $season, 'competitionName' => $matches[0]['competitionPart']['name'] ?? '']);
		$teamLink = url('druzstvo') . '?' . http_build_query(['team' => $team_id, 's' => $season, 'cat' => $team_name]);
		$next = $upcoming[0] ?? null;
		$last = $played ? end($played) : null;
		// panel Vytvoriť obrázok: najbližší a posledný zápas a obrázky družstva
		$mctx = ['myTeam' => $ownId, 'season' => $season, 'category' => $team_name];
		$grp = fn($m) => $m ? md_create_pane(md_create_match(sn_match_brief($m), $mctx), $team_id) : null;
		md_create_add($grp($next));
		md_create_add($grp($last));
		if ($matches) md_create_add(md_create_pane(md_create_team($team_id, ['name' => $tm['ownName'] ?: $team_name, 'logo' => $tm['ownLogo']], $team_name, $season, $matches[0]['competitionPart']['name'] ?? ''), $team_id));
	?>
	<div class="team-pane" data-team-pane="<?= plain($team_id) ?>"<?= $team_id === $sel ? '' : ' hidden' ?>>

		<?php if ($next || $last): ?>
		<div class="row g-3 mb-4">
			<?php foreach ([['Najbližší zápas', $next, false], ['Posledný zápas', $last, true]] as [$label, $m, $isRes]): if (!$m) continue;
				$p = $matchParams($m, $ownId);
				[$dT, $tT] = format_match_date($m['startDate'] ?? ''); ?>
			<div class="col-md-6">
				<div class="card-app h-100 spotlight">
					<div class="card-body">
						<div class="d-flex justify-content-between align-items-center mb-2">
							<span class="spot-label"><?= $label ?></span>
							<span class="small text-muted-2"><?= plain($dT) ?><?= $isRes ? '' : ' · ' . plain($tT) ?></span>
						</div>
						<div class="spot-teams">
							<a class="spot-team text-reset text-decoration-none" href="<?= plain(md_team_url($m['homeTeam']['_id'] ?? '', $season)) ?>"><?php if (!empty($m['homeTeam']['logoUrl'])): ?><img src="<?= plain($m['homeTeam']['logoUrl']) ?>" alt=""><?php endif; ?><span><?= plain($m['homeTeam']['name'] ?? '') ?></span></a>
							<a class="spot-score text-decoration-none" href="<?= plain(md_match_url($m['_id'] ?? '', $ownId)) ?>" title="Detail zápasu"><?= $isRes ? plain(($m['homeTeam']['score'] ?? '') . ':' . ($m['awayTeam']['score'] ?? '')) : 'vs' ?></a>
							<a class="spot-team away text-reset text-decoration-none" href="<?= plain(md_team_url($m['awayTeam']['_id'] ?? '', $season)) ?>"><?php if (!empty($m['awayTeam']['logoUrl'])): ?><img src="<?= plain($m['awayTeam']['logoUrl']) ?>" alt=""><?php endif; ?><span><?= plain($m['awayTeam']['name'] ?? '') ?></span></a>
						</div>
						<div class="d-flex flex-wrap gap-2 mt-3">
							<?php if ($isRes): ?>
								<a class="btn btn-primary btn-sm rounded-pill px-3" href="<?= plain(gen_link('result', $p)) ?>"><i class="bi bi-trophy me-1"></i>Výsledok</a>
							<?php else: ?>
								<a class="btn btn-primary btn-sm rounded-pill px-3" href="<?= plain(gen_link('matchday', $p)) ?>"><i class="bi bi-calendar-event me-1"></i>Match day</a>
							<?php endif; ?>
							<?= md_create_btn($grp($m), 'Ďalšie obrázky', 'btn btn-outline-primary btn-sm rounded-pill px-3') ?>
							<a class="btn btn-outline-secondary btn-sm rounded-pill px-3" href="<?= plain(md_match_url($m['_id'] ?? '', $ownId)) ?>"><i class="bi bi-info-circle me-1"></i>Detail zápasu</a>
						</div>
					</div>
				</div>
			</div>
			<?php endforeach; ?>
		</div>
		<?php endif; ?>

		<section class="card-app team-card mb-4" id="team-<?= plain($team_id) ?>">
			<div class="card-header">
				<div>
					<h2 class="team-title"><?= plain($team_name) ?></h2>
					<div class="small text-muted-2"><?= plain($matches[0]['competitionPart']['name'] ?? $season) ?> · <?= count($played) ?>/<?= count($matches) ?> odohraných</div>
				</div>
				<?php if ($matches): ?>
				<div class="d-flex flex-wrap gap-2">
					<a class="btn btn-sm btn-primary rounded-pill px-3" href="<?= plain($teamLink) ?>"><i class="bi bi-people me-1"></i>Družstvo</a>
					<a class="btn btn-sm btn-outline-primary rounded-pill px-3" href="<?= plain($squadLink) ?>" title="Obrázok so súpiskou družstva"><i class="bi bi-people me-1"></i>Súpiska</a>
				</div>
				<?php endif; ?>
			</div>

			<?php if (!$matches): ?>
				<div class="empty-state"><i class="bi bi-calendar-x"></i>Žiadne zápasy.</div>
			<?php else: ?>
			<div class="list-toolbar">
				<div class="btn-group filter-pills" role="group" aria-label="Filter zápasov">
					<button type="button" class="btn btn-outline-secondary active" data-filter="all">Všetky</button>
					<button type="button" class="btn btn-outline-secondary" data-filter="upcoming">Nadchádzajúce</button>
					<button type="button" class="btn btn-outline-secondary" data-filter="played">Odohraté</button>
				</div>
			</div>
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
					$params = $matchParams($value, $ownId);
					$zapis = md_match_url($value['_id'] ?? '', $ownId);
					$tu = fn($t) => md_team_url($t['_id'] ?? '', $season);
				?>
				<li class="match" data-state="<?= $isPlayed ? 'played' : 'upcoming' ?>">
					<div class="match-meta">
						<span class="round"><?= plain(preg_match('/kolo/i', $roundName) ? $roundName : $roundName . '. kolo') ?></span>
						<span><?= plain($dateTxt) ?> · <?= plain($timeTxt) ?></span>
						<?php if (!$isPlayed): ?><span class="badge rounded-pill text-bg-<?= $stateColor ?> bg-opacity-75"><?= plain($stateTxt) ?></span><?php endif; ?>
					</div>

					<div class="match-teams">
						<div class="match-team home <?= $win1 ? 'winner' : '' ?>">
							<a class="name text-reset text-decoration-none" href="<?= plain($tu($home)) ?>"><?= plain($home['name'] ?? '') ?></a>
							<?php if ($logo1): ?><img class="team-logo" src="<?= plain($logo1) ?>" alt="" loading="lazy"><?php else: ?><span class="team-logo empty"><i class="bi bi-shield"></i></span><?php endif; ?>
						</div>
						<?php if ($isPlayed): ?>
							<a class="match-score" href="<?= plain($zapis) ?>" title="Zápis o stretnutí"><?= plain($home['score'] ?? '') ?> : <?= plain($away['score'] ?? '') ?></a>
						<?php else: ?>
							<?php if ($isBye): ?><div class="match-score upcoming">–</div><?php else: ?><a class="match-score upcoming" href="<?= plain($zapis) ?>" title="Detail zápasu: forma, vzájomné zápasy, tabuľka"><?= plain($timeTxt) ?></a><?php endif; ?>
						<?php endif; ?>
						<div class="match-team away <?= $win2 ? 'winner' : '' ?>">
							<?php if ($logo2): ?><img class="team-logo" src="<?= plain($logo2) ?>" alt="" loading="lazy"><?php else: ?><span class="team-logo empty"><i class="bi bi-shield"></i></span><?php endif; ?>
							<a class="name text-reset text-decoration-none" href="<?= plain($tu($away)) ?>"><?= plain($away['name'] ?? '') ?></a>
						</div>
					</div>

					<div class="match-actions">
						<?php if (!$isBye): ?>
							<a class="btn btn-<?= $isPlayed ? 'outline-primary' : 'primary' ?>" href="<?= plain(gen_link($isPlayed ? 'result' : 'matchday', $params)) ?>"><i class="bi bi-<?= $isPlayed ? 'trophy' : 'calendar-event' ?> me-1"></i><?= $isPlayed ? 'Výsledok' : 'Match day' ?></a>
							<?= $params['matchID'] !== '' ? md_create_btn($grp($value), '', 'btn btn-outline-secondary btn-more') : '' ?>
						<?php endif; ?>
					</div>
				</li>
				<?php endforeach; ?>
			</ul>
			<div class="empty-state filter-empty" hidden><i class="bi bi-funnel"></i>Žiadne zápasy pre zvolený filter.</div>
			<?php endif; ?>
		</section>
	</div>
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
	// prepínanie družstiev bez načítania stránky
	$('[data-team-tab]').on('click', function (e) {
		e.preventDefault();
		const id = this.dataset.teamTab;
		$('[data-team-tab]').removeClass('active').removeAttr('aria-current');
		$(this).addClass('active').attr('aria-current', 'true');
		$('[data-team-pane]').each(function () { this.hidden = this.dataset.teamPane !== id; });
		if (window.MD && MD.create) MD.create.setPane(id);
		history.replaceState(null, '', this.href);
	});
	// zapamätať klub do "Naposledy hľadané"
	try {
		const MD = window.MD, list = (MD.store.pref('recent') || []).filter((r) => !(r.teamId === <?= json_encode($team) ?> && r.season === <?= json_encode($s) ?>));
		<?php if ($teams): ?>list.unshift({ teamId: <?= json_encode($team) ?>, teamName: <?= json_encode($clubName, JSON_UNESCAPED_UNICODE) ?>, season: <?= json_encode($s) ?>, logo: <?= json_encode($clubLogo) ?> });
		MD.store.pref('recent', list.slice(0, 6));<?php endif; ?>
	} catch (e) {}
});
</script>

<script>
(function () {
	const b = document.getElementById('favBtn');
	if (!b || !window.MD) return;
	const sync = () => {
		const f = MD.store.pref('favClub');
		const on = !!f && f.club === b.dataset.club;
		b.setAttribute('aria-pressed', on);
		b.classList.toggle('is-on', on);
		b.querySelector('i').className = 'bi ' + (on ? 'bi-star-fill' : 'bi-star') + ' me-1';
		b.querySelector('span').textContent = on ? 'Môj klub' : 'Nastaviť ako môj klub';
	};
	b.addEventListener('click', () => {
		const f = MD.store.pref('favClub');
		if (f && f.club === b.dataset.club) { MD.store.pref('favClub', null); MD.toast('Klub odobratý z úvodnej stránky', 'info'); }
		else { MD.store.pref('favClub', { club: b.dataset.club, name: b.dataset.name, logo: b.dataset.logo, season: b.dataset.season }); MD.toast('Najbližšie zápasy klubu uvidíte na úvodnej stránke'); }
		sync();
	});
	sync();
})();
</script>

<?php add_layout("footer"); ?>
