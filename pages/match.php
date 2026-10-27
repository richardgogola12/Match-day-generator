<?php
/**
 * Zápas: priebeh, štatistika vzájomných zápasov, forma, delegované osoby, štadión, zostavy, H2H a tabuľka.
 *   /zapas?matchID=…[&myTeam=…]   (ostatné údaje sa načítajú zo zápisu; staré odkazy s d1name… fungujú tiež)
 */
$id = (string)($_GET['matchID'] ?? '');
if (!preg_match('/^[A-Za-z0-9_\-]{6,64}$/', $id)) show_404();
@set_time_limit(90);
$doc = sn_fetch_cached(sprintf(API_MATCH, rawurlencode($id)), 300);
$err = !is_array($doc) || !empty($doc['error']);
$b = $err ? md_brief_from_get() : md_brief_from_doc($doc);
$b += ['competitionId' => '', 'partId' => '', 'season' => '', 'issf' => ''];
foreach (['home', 'away'] as $s) $b[$s] += ['club' => '', 'cat' => '', 'ageCategory' => '', 'gender' => ''];
// názvy z pôvodného odkazu, ak ich zápis nemá
foreach (['home' => 'd1', 'away' => 'd2'] as $s => $k) {
    if ($b[$s]['name'] === '' && !empty($_GET[$k . 'name'])) $b[$s]['name'] = (string)$_GET[$k . 'name'];
}
$myTeam = (string)($_GET['myTeam'] ?? '');
$season = $b['season'] ?: (string)($_GET['season'] ?? '');

$events  = $err ? [] : sportnet_events($doc, $b['home']['id']);
$lineups = $err ? null : sportnet_lineups($doc, $b['home']['id'], $b['away']['id']);
$rep     = $err ? [] : sportnet_report($doc);
$venue   = $err ? null : sportnet_venue($doc);
$offic   = $err ? [] : sportnet_officials($doc);
$len     = $err ? 90 : sn_match_length($doc);
$mins = [];
if ($lineups) foreach (['home', 'away'] as $s) $mins[$s] = sn_match_minutes($lineups[$s], $events, $b[$s]['id'], $len);
$h2h   = $err ? null : sportnet_h2h($b);
$form  = ['home' => sportnet_form($b['home']['id'], $b['date'], 5, $b['id']), 'away' => sportnet_form($b['away']['id'], $b['date'], 5, $b['id'])];
$table = $err ? [] : sportnet_match_table($b, $doc);
[$dT, $tT] = format_match_date($b['date']);

$params = md_match_params($b, $myTeam);
$gen = fn($type, array $extra = []) => md_gen_link($type, $params + $extra);
$teamUrl = fn($s) => md_team_url($b[$s]['id'], $season, $b[$s]['cat']);
$clubUrl = fn($s) => md_club_url($b[$s]['club'], $season, $b[$s]['id']);
$playerUrl = fn($pid, $name, $s) => md_player_url($pid, $b[$s]['id'], $season, $b[$s]['cat'], sn_name_key($name));
$sideOfTeam = fn($teamId) => $teamId === $b['away']['id'] ? 'away' : 'home';
// družstvo pre obrázok Tabuľka: "naše" družstvo, inak domáci
$tableSide = $myTeam === $b['away']['id'] ? 'away' : 'home';
$tableLink = md_gen_link('table', md_team_params($b[$tableSide]['id'], ['name' => $b[$tableSide]['name'], 'logo' => $b[$tableSide]['logo']], $b[$tableSide]['cat'], $season, $b['competition']));

$icon = ['goal' => '<i class="bi bi-circle-fill ev-goal"></i>', 'yellow' => '<span class="card-ic yellow"></span>', 'red' => '<span class="card-ic red"></span>', 'sub' => '<i class="bi bi-arrow-left-right ev-sub"></i>'];
$imgBtn = fn($href, $label = 'Obrázok', $icn = 'bi-image') => '<a class="btn btn-sm btn-outline-primary rounded-pill" href="' . plain($href) . '"><i class="bi ' . $icn . ' me-1"></i>' . $label . '</a>';
$link = fn($href, $text, $cls = '') => $href ? '<a class="' . $cls . '" href="' . plain($href) . '">' . plain($text) . '</a>' : '<span class="' . $cls . '">' . plain($text) . '</span>';
$pl = fn($pid, $name, $s, $strong = false) => ($u = $playerUrl($pid, $name, $s)) ? '<a class="player-link' . ($strong ? ' fw-semibold' : '') . '" href="' . plain($u) . '">' . plain($name) . '</a>' : plain($name);

$sections = [['priebeh', 'Priebeh'], ['statistika', 'Štatistika'], ['forma', 'Forma'], ['info', 'Delegované osoby a štadión'], ['zostavy', 'Zostavy'], ['h2h', 'Vzájomné zápasy'], ['tabulka', 'Tabuľka']];

// panel Vytvoriť obrázok: zápas, "naše" družstvo a klub
md_create_add(md_create_match($b, ['myTeam' => $myTeam, 'season' => $season, 'category' => $b[$tableSide]['cat']]));
if ($b[$tableSide]['id'] !== '') md_create_add(md_create_team($b[$tableSide]['id'], ['name' => $b[$tableSide]['name'], 'logo' => $b[$tableSide]['logo']], $b[$tableSide]['cat'], $season, $b['competition']));
if ($b[$tableSide]['club'] !== '') md_create_add(md_create_club(sn_club_slug($b[$tableSide]['club']), $b[$tableSide]['name'], $b[$tableSide]['logo'], $season));
add_layout("header", array("title" => $b['home']['name'] . ' – ' . $b['away']['name'] . " | zápas | Match Day"));
?>

<section class="hero hero-sm">
	<div class="container-xxl">
		<div class="d-flex flex-wrap gap-2 mb-3">
			<?php if ($b['competition']): ?><span class="hero-eyebrow"><i class="bi bi-trophy"></i> <?= plain($b['competition']) ?></span><?php endif; ?>
			<?php if ($b['round']): ?><span class="hero-eyebrow"><i class="bi bi-flag"></i> <?= plain(preg_match('/kolo/i', $b['round']) ? $b['round'] : $b['round'] . '. kolo') ?></span><?php endif; ?>
			<?php if ($dT): ?><span class="hero-eyebrow"><i class="bi bi-calendar3"></i> <?= plain($dT) ?> · <?= plain($tT) ?></span><?php endif; ?>
			<?php if (!empty($venue['name'])): ?><span class="hero-eyebrow"><i class="bi bi-geo-alt"></i> <?= plain($venue['name']) ?></span><?php endif; ?>
		</div>
		<div class="scoreboard">
			<?php foreach (['home', 'away'] as $i => $s): if ($i === 1): ?>
				<div class="sb-score">
					<?= $b['played'] ? plain($b['home']['score']) . '<span>:</span>' . plain($b['away']['score']) : '<small>' . plain($tT ?: 'vs') . '</small>' ?>
					<?php if (!empty($rep['halftime'])): ?><div class="sb-half">polčas <?= plain($rep['halftime']) ?></div><?php endif; ?>
				</div>
			<?php endif; ?>
				<a class="sb-team" href="<?= plain($teamUrl($s) ?: '#') ?>" title="Prehľad družstva">
					<?php if ($b[$s]['logo']): ?><img src="<?= plain(url('api-logo') . '?url=' . rawurlencode($b[$s]['logo'])) ?>" alt="" width="72" height="72"><?php endif; ?>
					<span><?= plain($b[$s]['name']) ?></span>
					<?php if ($b[$s]['cat']): ?><small><?= plain($b[$s]['cat']) ?></small><?php endif; ?>
				</a>
			<?php endforeach; ?>
		</div>
	</div>
</section>

<div class="container-xxl overlap">
	<?php if ($err): ?>
		<div class="card-app mb-4"><div class="card-body"><div class="alert alert-warning mb-0"><i class="bi bi-exclamation-triangle me-2"></i>Zápis o stretnutí sa nepodarilo načítať zo Sportnetu.</div></div></div>
	<?php endif; ?>

	<nav class="section-nav mb-4" aria-label="Časti stránky zápasu">
		<?php foreach ($sections as [$k, $lab]): ?><a href="#<?= $k ?>"><?= $lab ?></a><?php endforeach; ?>
		<span class="ms-auto d-none d-lg-flex gap-2">
			<?php foreach (['home', 'away'] as $s): if ($b[$s]['club']): ?><a class="club-mini" href="<?= plain($clubUrl($s)) ?>" title="Stránka klubu"><i class="bi bi-shield"></i> <?= plain($b[$s]['name']) ?></a><?php endif; endforeach; ?>
		</span>
	</nav>

	<!-- PRIEBEH -->
	<section class="card-app mb-4 match-section" id="priebeh">
		<div class="card-header sec-head">
			<h2 class="team-title mb-0">Priebeh zápasu</h2>
			<div class="d-flex flex-wrap gap-2">
				<?php if ($b['played']): ?>
					<?= $imgBtn($gen('result'), 'Výsledok', 'bi-trophy') ?>
					<?= $imgBtn($gen('goals'), 'Strelci', 'bi-list-ol') ?>
					<?= $imgBtn($gen('report'), 'Súhrn', 'bi-file-text') ?>
				<?php else: ?>
					<?= $imgBtn($gen('matchday'), 'Match day', 'bi-calendar-event') ?>
				<?php endif; ?>
			</div>
		</div>
		<?php if (!$events): ?>
			<div class="empty-state"><i class="bi bi-clock-history"></i><?= $b['played'] ? 'Zápis neobsahuje udalosti.' : 'Zápas sa ešte nehral.' ?></div>
		<?php else: ?>
		<ul class="timeline">
			<?php foreach ($events as $e): $right = $e['side'] === 'away'; $evSide = $sideOfTeam($e['team']); ?>
				<li class="<?= $right ? 'away' : 'home' ?> ev-<?= $e['kind'] ?>">
					<span class="min"><?= (int)$e['minute'] ?>'</span>
					<span class="ic"><?= $icon[$e['kind']] ?></span>
					<span class="tx">
						<?php if ($e['kind'] === 'sub'): ?>
							<span class="text-success"><i class="bi bi-arrow-up-short"></i><?= $pl($e['inId'] ?? '', $e['in'], $evSide) ?></span> <span class="text-danger"><i class="bi bi-arrow-down-short"></i><?= $pl($e['id'], $e['name'], $evSide) ?></span>
						<?php else: ?>
							<strong><?= $pl($e['id'], $e['name'], $evSide) ?></strong><?= !empty($e['own']) ? ' <span class="text-muted-2">(vlastný gól)</span>' : '' ?><?= !empty($e['penalty']) ? ' <span class="text-muted-2">(pokutový kop)</span>' : '' ?>
						<?php endif; ?>
					</span>
				</li>
			<?php endforeach; ?>
		</ul>
		<?php endif; ?>
	</section>

	<!-- ŠTATISTIKA -->
	<section class="card-app mb-4 match-section" id="statistika">
		<div class="card-header sec-head">
			<h2 class="team-title mb-0">Štatistika vzájomných zápasov</h2>
			<?= $imgBtn($gen('h2h'), 'Obrázok', 'bi-bar-chart-steps') ?>
		</div>
		<?php if (!$h2h || !$h2h['played']): ?>
			<div class="empty-state"><i class="bi bi-people"></i>Tímy spolu v tejto kategórii zatiaľ nehrali.</div>
		<?php else: ?>
		<div class="card-body">
			<div class="cmp-head">
				<a href="<?= plain($teamUrl('home')) ?>" title="<?= plain($b['home']['name']) ?>"><?php if ($b['home']['logo']): ?><img src="<?= plain(url('api-logo') . '?url=' . rawurlencode($b['home']['logo'])) ?>" alt="<?= plain($b['home']['name']) ?>"><?php endif; ?></a>
				<div class="text-center"><div class="small text-muted-2">Počet zápasov</div><div class="cmp-total"><?= (int)$h2h['played'] ?></div></div>
				<a href="<?= plain($teamUrl('away')) ?>" title="<?= plain($b['away']['name']) ?>"><?php if ($b['away']['logo']): ?><img src="<?= plain(url('api-logo') . '?url=' . rawurlencode($b['away']['logo'])) ?>" alt="<?= plain($b['away']['name']) ?>"><?php endif; ?></a>
			</div>
			<?php foreach ([['Výhry', 'w'], ['Prehry', 'l'], ['Remízy', 'd'], ['Góly', 'gf'], ['Červené karty', 'red'], ['Žlté karty', 'yellow']] as [$lab, $k]):
				$hv = (int)$h2h['home'][$k]; $av = (int)$h2h['away'][$k]; $sum = max(1, $hv + $av); ?>
				<div class="cmp-row">
					<div class="cmp-vals"><strong><?= $hv ?></strong><span><?= $lab ?></span><strong><?= $av ?></strong></div>
					<div class="cmp-bars"><div class="l"><i style="width:<?= round($hv / $sum * 100) ?>%"></i></div><div class="r"><i style="width:<?= round($av / $sum * 100) ?>%"></i></div></div>
				</div>
			<?php endforeach; ?>
		</div>
		<?php endif; ?>
	</section>

	<!-- FORMA -->
	<section class="card-app mb-4 match-section" id="forma">
		<div class="card-header sec-head">
			<h2 class="team-title mb-0">Forma</h2>
			<div class="d-flex flex-wrap gap-2">
				<?= $imgBtn($gen('form', ['side' => 'home']), 'Obrázok – domáci') ?>
				<?= $imgBtn($gen('form', ['side' => 'away']), 'Obrázok – hostia') ?>
			</div>
		</div>
		<div class="card-body">
			<ul class="nav nav-pills form-tabs mb-3" role="tablist">
				<?php foreach (['home', 'away'] as $i => $s): ?>
					<li class="nav-item" role="presentation"><button class="nav-link<?= $i === 0 ? ' active' : '' ?>" data-bs-toggle="pill" data-bs-target="#form-<?= $s ?>" type="button" role="tab"><?= plain($b[$s]['name']) ?></button></li>
				<?php endforeach; ?>
			</ul>
			<div class="tab-content">
				<?php foreach (['home', 'away'] as $i => $s): ?>
					<div class="tab-pane fade<?= $i === 0 ? ' show active' : '' ?>" id="form-<?= $s ?>" role="tabpanel">
						<?php if (!$form[$s]): ?><div class="text-muted-2 small">Zatiaľ žiadne odohraté zápasy.</div><?php endif; ?>
						<div class="form-list">
							<?php foreach ($form[$s] as $m): [$fd, $ft] = format_match_date($m['date']); ?>
								<a class="form-item" href="<?= plain(md_match_url($m['id'], $b[$s]['id'])) ?>">
									<span class="when"><?= plain($fd) ?><small><?= plain($ft) ?></small></span>
									<span class="teams">
										<span class="<?= $m['home']['id'] === $b[$s]['id'] ? 'fw-bold' : '' ?>"><?php if ($m['home']['logo']): ?><img src="<?= plain($m['home']['logo']) ?>" alt="" loading="lazy"><?php endif; ?><em><?= plain($m['home']['name']) ?></em><b><?= (int)$m['home']['score'] ?></b></span>
										<span class="<?= $m['away']['id'] === $b[$s]['id'] ? 'fw-bold' : '' ?>"><?php if ($m['away']['logo']): ?><img src="<?= plain($m['away']['logo']) ?>" alt="" loading="lazy"><?php endif; ?><em><?= plain($m['away']['name']) ?></em><b><?= (int)$m['away']['score'] ?></b></span>
									</span>
									<span class="res res-<?= plain($m['result']) ?>"><?= plain($m['result']) ?></span>
								</a>
							<?php endforeach; ?>
						</div>
					</div>
				<?php endforeach; ?>
			</div>
		</div>
	</section>

	<!-- DELEGOVANÉ OSOBY + ŠTADIÓN -->
	<div class="row g-4 mb-4 match-section" id="info">
		<div class="col-lg-6">
			<div class="card-app h-100">
				<div class="card-header sec-head"><h2 class="team-title mb-0">Delegované osoby</h2><?= $b['played'] ? $imgBtn($gen('report'), 'Súhrn', 'bi-file-text') : '' ?></div>
				<div class="card-body">
					<dl class="kv mb-0">
						<?php foreach ($offic as $o): ?><dt><?= plain($o['role']) ?></dt><dd><?= plain($o['name']) ?></dd><?php endforeach; ?>
						<?php if (($rep['spectators'] ?? '') !== ''): ?><dt>Diváci</dt><dd><?= plain($rep['spectators']) ?></dd><?php endif; ?>
						<?php if (!empty($b['issf'])): ?><dt>ID zápasu</dt><dd><?= plain($b['issf']) ?></dd><?php endif; ?>
						<?php if (!$offic && empty($b['issf'])): ?><dt>–</dt><dd class="text-muted-2">Zatiaľ bez údajov</dd><?php endif; ?>
					</dl>
				</div>
			</div>
		</div>
		<div class="col-lg-6">
			<div class="card-app h-100">
				<div class="card-header sec-head"><h2 class="team-title mb-0">Štadión</h2><?= $imgBtn($gen('matchday'), 'Match day', 'bi-calendar-event') ?></div>
				<div class="card-body">
					<?php if (!$venue): ?><div class="text-muted-2 small">Štadión nie je v zápise uvedený.</div><?php else: ?>
					<dl class="kv mb-0">
						<dt>Názov</dt><dd><?= plain($venue['name']) ?></dd>
						<?php foreach (['length' => ['Dĺžka', ' metrov'], 'width' => ['Šírka', ' metrov'], 'capacity' => ['Celková kapacita', ''], 'guests' => ['Hosťovský sektor', ''], 'seats' => ['Miest na sedenie', ''], 'vip' => ['VIP', '']] as $k => [$lab, $suf]):
							if ($venue[$k] === '') continue; ?>
							<dt><?= $lab ?></dt><dd><?= plain($venue[$k] . $suf) ?></dd>
						<?php endforeach; ?>
						<?php if ($venue['owner']): ?><dt>Majiteľ</dt><dd><?= plain($venue['owner']) ?></dd><?php endif; ?>
						<?php if ($venue['address']): ?><dt>Adresa</dt><dd><?php if ($venue['geo']): ?><a href="https://www.google.com/maps/search/?api=1&amp;query=<?= plain($venue['geo'][0] . ',' . $venue['geo'][1]) ?>" target="_blank" rel="noopener"><?= plain($venue['address']) ?> <i class="bi bi-box-arrow-up-right small"></i></a><?php else: ?><?= plain($venue['address']) ?><?php endif; ?></dd><?php endif; ?>
					</dl>
					<?php endif; ?>
				</div>
			</div>
		</div>
	</div>

	<!-- ZOSTAVY -->
	<section class="mb-4 match-section" id="zostavy">
		<div class="row g-4">
			<?php foreach (['home', 'away'] as $s): $L = $lineups[$s] ?? null; ?>
				<div class="col-md-6">
					<div class="card-app h-100">
						<div class="card-header sec-head">
							<h2 class="team-title mb-0"><?= $link($teamUrl($s), $b[$s]['name'], 'text-reset') ?></h2>
							<?= $imgBtn($gen('lineup', ['side' => $s]), 'Zostava', 'bi-person-lines-fill') ?>
						</div>
						<div class="card-body">
							<?php if (!$L || (!$L['start'] && !$L['subs'])): ?><div class="text-muted-2 small">Zostava zatiaľ nie je v zápise.</div><?php else: ?>
							<?php foreach (['start' => 'Základná zostava', 'subs' => 'Náhradníci'] as $k => $lab): if (!$L[$k]) continue; ?>
								<h3 class="h6 section-title text-uppercase text-muted-2 mb-2 <?= $k === 'subs' ? 'mt-3' : '' ?>"><?= $lab ?></h3>
								<ol class="players-mini" style="columns:1">
									<?php foreach ($L[$k] as $p): $mm = $mins[$s][$p['id'] !== '' ? 'id:' . $p['id'] : 'n:' . sn_name_key($p['name'])] ?? null; ?>
										<li class="<?= ($mm['role'] ?? '') === 'bench' ? 'is-bench' : '' ?>"><span class="nr"><?= plain($p['nr'] ?: '–') ?></span><?= $pl($p['id'], $p['name'], $s) ?><?= $p['captain'] ? ' <span class="badge text-bg-warning">C</span>' : '' ?><?= $p['gk'] ? ' <span class="badge text-bg-secondary">B</span>' : '' ?>
											<?php if (($mm['role'] ?? '') === 'sub'): ?><span class="pm-in" title="Nastúpil v <?= (int)$mm['in'] ?>. minúte"><i class="bi bi-arrow-up-short"></i><?= (int)$mm['in'] ?>'</span><?php endif; ?>
											<?php if (($mm['out'] ?? null) !== null && ($mm['role'] ?? '') !== 'bench'): ?><span class="pm-out" title="Odišiel v <?= (int)$mm['out'] ?>. minúte"><i class="bi bi-arrow-down-short"></i><?= (int)$mm['out'] ?>'</span><?php endif; ?>
										</li>
									<?php endforeach; ?>
								</ol>
							<?php endforeach; ?>
							<?php if ($L['coach']): ?><div class="small mt-3"><span class="text-muted-2">Tréner:</span> <strong><?= plain($L['coach']) ?></strong></div><?php endif; ?>
							<?php endif; ?>
						</div>
					</div>
				</div>
			<?php endforeach; ?>
		</div>
	</section>

	<!-- H2H -->
	<section class="card-app mb-4 match-section overflow-hidden" id="h2h">
		<div class="card-header sec-head">
			<h2 class="team-title mb-0">Vzájomné zápasy</h2>
			<?= $imgBtn($gen('h2h'), 'Obrázok', 'bi-bar-chart-steps') ?>
		</div>
		<?php if (!$h2h || !$h2h['matches']): ?>
			<div class="empty-state"><i class="bi bi-arrow-left-right"></i>Žiadne vzájomné zápasy.</div>
		<?php else: ?>
		<ul class="mini-matches">
			<?php foreach ($h2h['matches'] as $m): [$hd] = format_match_date($m['date']); ?>
				<li>
					<a href="<?= plain(md_match_url($m['id'], $myTeam)) ?>">
						<span class="when"><?= plain($hd) ?><small><?= plain($m['competition']) ?></small></span>
						<span class="h"><?= plain($m['home']['name']) ?></span>
						<span class="sc"><?= (int)$m['home']['score'] ?>:<?= (int)$m['away']['score'] ?></span>
						<span class="a"><?= plain($m['away']['name']) ?></span>
					</a>
				</li>
			<?php endforeach; ?>
		</ul>
		<?php endif; ?>
	</section>

	<!-- TABUĽKA -->
	<section class="card-app mb-4 match-section overflow-hidden" id="tabulka">
		<div class="card-header sec-head">
			<h2 class="team-title mb-0">Tabuľka<?= $b['competition'] ? ' <span class="fw-normal text-muted-2 small">· ' . plain($b['competition']) . '</span>' : '' ?></h2>
			<?= $imgBtn($tableLink, 'Obrázok', 'bi-list-ol') ?>
		</div>
		<?php if (!$table): ?>
			<div class="empty-state"><i class="bi bi-list-ol"></i>Tabuľka súťaže nie je k dispozícii.</div>
		<?php else: ?>
		<div class="table-responsive">
			<table class="table align-middle mb-0 data-table standings">
				<thead><tr><th>#</th><th>Družstvo</th><th class="text-end" title="Zápasy">Z</th><th class="text-end" title="Výhry">V</th><th class="text-end" title="Remízy">R</th><th class="text-end" title="Prehry">P</th><th class="text-end">Skóre</th><th class="text-end" title="Body">B</th><th class="d-none d-md-table-cell">Forma</th></tr></thead>
				<tbody>
					<?php foreach ($table as $r): ?>
						<tr class="<?= $r['own'] ? 'is-own' : '' ?>">
							<td class="fw-bold"><?= (int)$r['pos'] ?>.</td>
							<td><a class="d-inline-flex align-items-center gap-2 text-reset text-decoration-none" href="<?= plain(md_team_url($r['id'], $season)) ?>"><?php if ($r['logo']): ?><img src="<?= plain(url('api-logo') . '?url=' . rawurlencode($r['logo'])) ?>" alt="" width="26" height="26" loading="lazy"><?php endif; ?><span class="fw-semibold"><?= plain($r['name']) ?></span></a></td>
							<td class="text-end"><?= (int)$r['p'] ?></td><td class="text-end"><?= (int)$r['w'] ?></td><td class="text-end"><?= (int)$r['d'] ?></td><td class="text-end"><?= (int)$r['l'] ?></td>
							<td class="text-end text-nowrap"><?= (int)$r['gf'] ?>:<?= (int)$r['ga'] ?></td>
							<td class="text-end fw-bold"><?= (int)$r['pts'] ?></td>
							<td class="d-none d-md-table-cell"><?= md_form_badges($r['form'] ?? []) ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<?php endif; ?>
	</section>
</div>

<script>
// zvýraznenie aktuálnej sekcie v navigácii
(function () {
	const links = [...document.querySelectorAll('.section-nav > a')];
	if (!('IntersectionObserver' in window) || !links.length) return;
	const io = new IntersectionObserver((entries) => {
		entries.forEach((e) => {
			if (!e.isIntersecting) return;
			links.forEach((a) => a.classList.toggle('active', a.getAttribute('href') === '#' + e.target.id));
		});
	}, { rootMargin: '-45% 0px -50% 0px' });
	links.forEach((a) => { const el = document.querySelector(a.getAttribute('href')); if (el) io.observe(el); });
})();
</script>

<?php add_layout("footer"); ?>
