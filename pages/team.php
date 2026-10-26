<?php
/**
 * Prehľad družstva: hráči, tabuľka súťaže, kolá a štatistiky.
 *   /druzstvo?team=<ID družstva>&s=<sezóna>&cat=<kategória>
 */
$teamId = (string)($_GET['team'] ?? '');
$season = (string)($_GET['s'] ?? '');
$cat    = (string)($_GET['cat'] ?? '');
if (!preg_match('/^[A-Za-z0-9_\-.]{2,80}$/', $teamId)) show_404();
@set_time_limit(120);

$T = sportnet_team($teamId);
$C = sportnet_competition($teamId);
$error = $T['error'] ?? ($C['error'] ?? null);
$team = ($T['team'] ?? ($C['team'] ?? [])) + ['id' => '', 'name' => '', 'logo' => '', 'club' => ''];
if ($team['club'] === '') $team['club'] = (string)($C['team']['club'] ?? '');
$players = $T['players'] ?? [];
$st = $T['stats'] ?? [];
$compName = $C['competition'] ?? ($T['competition'] ?? '');
$tp = md_team_params($teamId, $team, $cat, $season, $compName);
$q = fn(array $extra = []) => http_build_query(['team' => $teamId, 's' => $season, 'cat' => $cat] + $extra);

$rounds = $C['rounds'] ?? [];
$roundSel = isset($_GET['kolo']) ? (int)$_GET['kolo'] : null;
$curIdx = $C['nextRound'] ?? ($C['lastRound'] ?? 0);
if ($roundSel !== null) foreach ($rounds as $i => $r) if ($r['no'] === $roundSel) $curIdx = $i;
$tab = $_GET['tab'] ?? 'prehlad';
if (!in_array($tab, ['prehlad', 'hraci', 'tabulka', 'kola'], true)) $tab = 'prehlad';

$hasMin = !empty($T['hasMinutes']);
$leaders = [
	['goals', 'Strelci', 'bi-bullseye', 'gólov'],
	$hasMin ? ['minutes', 'Najviac minút', 'bi-stopwatch', 'min.'] : ['apps', 'Najviac zápasov', 'bi-calendar-check', 'zápasov'],
	['yellow', 'Žlté karty', 'bi-square-fill text-warning', 'ŽK'],
];

add_layout("header", array("title" => ($team['name'] ?: 'Družstvo') . " – prehľad | Match Day"));
?>

<section class="hero hero-sm">
	<div class="container-xxl">
		<?php if ($team['club']): ?><a href="<?= plain(md_club_url($team['club'], $season, $teamId)) ?>" class="btn btn-sm btn-outline-light rounded-pill mb-3"><i class="bi bi-arrow-left me-1"></i> Zápasy klubu</a><?php else: ?><button type="button" onclick="history.length > 1 ? history.back() : location.href='<?= url('/') ?>'" class="btn btn-sm btn-outline-light rounded-pill mb-3"><i class="bi bi-arrow-left me-1"></i> Späť</button><?php endif; ?>
		<div class="d-flex align-items-center gap-3">
			<?php if ($team['logo']): ?><img class="hero-logo" src="<?= plain(url('api-logo') . '?url=' . rawurlencode($team['logo'])) ?>" alt="" width="64" height="64"><?php endif; ?>
			<div>
				<h1 class="mb-1"><?= plain($team['name'] ?: 'Družstvo') ?> <span class="accent"><?= plain($cat) ?></span></h1>
				<div class="d-flex flex-wrap gap-2">
					<?php if ($team['club']): ?><a class="hero-eyebrow hero-link" href="<?= plain(md_club_url($team['club'], $season, $teamId)) ?>"><i class="bi bi-shield"></i> Klub a zápasy</a><?php endif; ?>
					<?php if ($season): ?><span class="hero-eyebrow"><i class="bi bi-calendar3"></i> <?= plain($season) ?></span><?php endif; ?>
					<?php if ($compName): ?><span class="hero-eyebrow"><i class="bi bi-trophy"></i> <?= plain($compName) ?></span><?php endif; ?>
					<?php if (!empty($T['coach'])): ?><span class="hero-eyebrow"><i class="bi bi-person-badge"></i> Tréner <?= plain($T['coach']) ?></span><?php endif; ?>
				</div>
			</div>
		</div>
	</div>
</section>

<div class="container-xxl overlap">
	<?php if ($error): ?>
		<div class="card-app mb-4"><div class="card-body"><div class="alert alert-danger mb-0"><i class="bi bi-exclamation-octagon me-2"></i><?= plain($error) ?></div></div></div>
	<?php endif; ?>

	<div class="card-app mb-4">
		<div class="card-body py-3 d-flex flex-wrap gap-2 align-items-center justify-content-between">
			<ul class="nav nav-pills team-tabs" role="tablist">
				<?php foreach (['prehlad' => ['bi-speedometer2', 'Prehľad'], 'hraci' => ['bi-people', 'Hráči'], 'tabulka' => ['bi-list-ol', 'Tabuľka'], 'kola' => ['bi-calendar-week', 'Kolá']] as $k => [$ic, $lab]): ?>
					<li class="nav-item" role="presentation"><button class="nav-link<?= $tab === $k ? ' active' : '' ?>" data-bs-toggle="pill" data-bs-target="#t-<?= $k ?>" data-tab="<?= $k ?>" type="button" role="tab"><i class="bi <?= $ic ?> me-1"></i><?= $lab ?></button></li>
				<?php endforeach; ?>
			</ul>
			<div class="dropdown">
				<button class="btn btn-primary dropdown-toggle rounded-pill" type="button" data-bs-toggle="dropdown" aria-expanded="false"><i class="bi bi-image me-1"></i>Vytvoriť obrázok</button>
				<ul class="dropdown-menu dropdown-menu-end app-dropdown">
					<li><a class="dropdown-item" href="<?= plain(md_gen_link('squad', $tp)) ?>"><i class="bi bi-people"></i>Súpiska</a></li>
					<li><a class="dropdown-item" href="<?= plain(md_gen_link('table', $tp)) ?>"><i class="bi bi-list-ol"></i>Tabuľka súťaže</a></li>
					<li><a class="dropdown-item" href="<?= plain(md_gen_link('round', $tp + ['roundNo' => $rounds[$curIdx]['no'] ?? ''])) ?>"><i class="bi bi-calendar-week"></i>Program / výsledky kola</a></li>
					<li><a class="dropdown-item" href="<?= plain(md_gen_link('stats', $tp)) ?>"><i class="bi bi-bar-chart-line"></i>Štatistiky družstva</a></li>
				</ul>
			</div>
		</div>
	</div>

	<div class="tab-content">

		<!-- PREHĽAD -->
		<div class="tab-pane fade<?= $tab === 'prehlad' ? ' show active' : '' ?>" id="t-prehlad" role="tabpanel">
			<div class="row g-3 mb-4">
				<?php
				$own = null;
				foreach ($C['table'] ?? [] as $r) if (!empty($r['own'])) $own = $r;
				$tiles = [
					[$own ? $own['pos'] . '.' : '–', 'miesto v tabuľke'],
					[$own ? $own['pts'] : '–', 'bodov'],
					[($st['w'] ?? 0) . ' / ' . ($st['d'] ?? 0) . ' / ' . ($st['l'] ?? 0), 'výhry / remízy / prehry'],
					[($st['gf'] ?? 0) . ':' . ($st['ga'] ?? 0), 'skóre'],
					[$st['clean'] ?? 0, 'zápasov bez inkasovaného gólu'],
					[!empty($st['spectMatches']) ? round($st['spectators'] / $st['spectMatches']) : '–', 'priemerne divákov'],
				];
				foreach ($tiles as [$n, $l]): ?>
					<div class="col-6 col-md-4 col-xl-2"><div class="stat-tile"><div class="stat-num"><?= plain($n) ?></div><div class="stat-label"><?= plain($l) ?></div></div></div>
				<?php endforeach; ?>
			</div>

			<div class="row g-4">
				<div class="col-md-6 col-xl-3">
					<div class="card-app h-100"><div class="card-body">
						<h2 class="h6 section-title text-uppercase text-muted-2 mb-3">Forma a bilancia</h2>
						<div class="mb-3"><?= md_form_badges($st['form'] ?? []) ?: '<span class="text-muted-2">–</span>' ?> <span class="small text-muted-2 ms-1">posledných 5 zápasov</span></div>
						<dl class="kv mb-0">
							<dt>Doma</dt><dd><?= (int)($st['home']['w'] ?? 0) ?> V · <?= (int)($st['home']['d'] ?? 0) ?> R · <?= (int)($st['home']['l'] ?? 0) ?> P</dd>
							<dt>Vonku</dt><dd><?= (int)($st['away']['w'] ?? 0) ?> V · <?= (int)($st['away']['d'] ?? 0) ?> R · <?= (int)($st['away']['l'] ?? 0) ?> P</dd>
							<dt>Žlté / červené karty</dt><dd><?= (int)($st['yellow'] ?? 0) ?> / <?= (int)($st['red'] ?? 0) ?></dd>
							<?php if (!empty($st['bigWin'])): ?><dt>Najvyššia výhra</dt><dd><?= plain($st['bigWin']['score']) ?> <span class="fw-normal text-muted-2">– <?= plain($st['bigWin']['opponent']) ?></span></dd><?php endif; ?>
							<dt>Hráčov nastúpilo</dt><dd><?= count(array_filter($players, fn($p) => $p['apps'] > 0)) ?></dd>
						</dl>
					</div></div>
				</div>
				<?php foreach ($leaders as [$f, $title, $icon, $unit]): $list = sn_leaders($players, $f, 5); ?>
					<div class="col-md-6 col-xl-3">
						<div class="card-app h-100"><div class="card-body">
							<h2 class="h6 section-title text-uppercase text-muted-2 mb-3"><i class="bi <?= $icon ?> me-1"></i><?= $title ?></h2>
							<?php if (!$list): ?><div class="text-muted-2 small">Zatiaľ žiadne údaje.</div><?php endif; ?>
							<ol class="leader-list">
								<?php foreach ($list as $p): ?>
									<li><a href="<?= plain(md_player_url($p['id'], $teamId, $season, $cat, $p['key'])) ?>"><span class="nr"><?= plain($p['nr'] ?: '–') ?></span><span class="nm"><?= plain($p['name']) ?></span></a><span class="val"><?= (int)$p[$f] ?> <small><?= $unit ?></small></span></li>
								<?php endforeach; ?>
							</ol>
						</div></div>
					</div>
				<?php endforeach; ?>
			</div>
			<p class="small text-muted-2 mt-3 mb-0"><i class="bi bi-info-circle me-1"></i><?= !empty($T['officialStats']) ? 'Minúty, zápasy a štarty sú oficiálne štatistiky Sportnetu; ostatné údaje zo zápisov o stretnutí' : 'Štatistiky sú spočítané zo zápisov o stretnutí na Sportnete' ?> (<?= (int)($T['matchesUsed'] ?? 0) ?> z <?= (int)($T['played'] ?? 0) ?> odohratých zápasov).</p>
		</div>

		<!-- HRÁČI -->
		<div class="tab-pane fade<?= $tab === 'hraci' ? ' show active' : '' ?>" id="t-hraci" role="tabpanel">
			<div class="card-app overflow-hidden">
				<?php if (!$players): ?>
					<div class="empty-state"><i class="bi bi-people"></i>V zápisoch o stretnutí sa zatiaľ nenašli žiadni hráči.</div>
				<?php else: ?>
				<div class="table-responsive">
					<table class="table table-hover align-middle mb-0 data-table sortable">
						<thead><tr>
							<th data-sort="num">#</th><th data-sort="text">Hráč</th>
							<th data-sort="num" class="text-end" title="Odohrané zápasy">Z</th><th data-sort="num" class="text-end" title="V základnej zostave">Š</th><?php if ($hasMin): ?><th data-sort="num" class="text-end" title="Odohrané minúty">Min</th><?php endif; ?><th data-sort="num" class="text-end" title="Góly">G</th><th data-sort="num" class="text-end" title="Góly z pokutového kopu">PK</th><th data-sort="num" class="text-end" title="Žlté karty">ŽK</th><th data-sort="num" class="text-end" title="Červené karty">ČK</th><th></th>
						</tr></thead>
						<tbody>
							<?php foreach ($players as $p): $pl = md_player_url($p['id'], $teamId, $season, $cat, $p['key']); ?>
								<tr>
									<td class="fw-bold text-primary" data-v="<?= $p['nr'] === '' ? 999 : (int)$p['nr'] ?>"><?= plain($p['nr'] ?: '–') ?></td>
									<td data-v="<?= plain($p['key']) ?>"><a class="fw-semibold text-reset text-decoration-none" href="<?= plain($pl) ?>"><?= plain($p['name']) ?></a>
										<?= $p['captain'] ? '<span class="badge text-bg-warning ms-1">C</span>' : '' ?><?= $p['gk'] ? '<span class="badge text-bg-secondary ms-1">B</span>' : '' ?></td>
									<?php foreach (array_merge(['apps', 'starts'], $hasMin ? ['minutes'] : [], ['goals', 'penalties', 'yellow', 'red']) as $f): ?>
										<td class="text-end" data-v="<?= (int)$p[$f] ?>"><?= (int)$p[$f] ?: '<span class="text-muted-2">0</span>' ?></td>
									<?php endforeach; ?>
									<td class="text-end"><a class="btn btn-sm btn-outline-primary rounded-pill" href="<?= plain($pl) ?>">Profil</a></td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>
				<?php endif; ?>
			</div>
		</div>

		<!-- TABUĽKA -->
		<div class="tab-pane fade<?= $tab === 'tabulka' ? ' show active' : '' ?>" id="t-tabulka" role="tabpanel">
			<div class="card-app overflow-hidden">
				<?php if (empty($C['table'])): ?>
					<div class="empty-state"><i class="bi bi-list-ol"></i>Tabuľku sa nepodarilo zostaviť.</div>
				<?php else: ?>
				<div class="table-responsive">
					<table class="table align-middle mb-0 data-table standings">
						<thead><tr><th>#</th><th>Družstvo</th><th class="text-end" title="Zápasy">Z</th><th class="text-end" title="Výhry">V</th><th class="text-end" title="Remízy">R</th><th class="text-end" title="Prehry">P</th><th class="text-end">Skóre</th><th class="text-end" title="Body">B</th><th class="d-none d-md-table-cell">Forma</th></tr></thead>
						<tbody>
							<?php foreach ($C['table'] as $r): ?>
								<tr class="<?= !empty($r['own']) ? 'is-own' : '' ?>">
									<td class="fw-bold"><?= (int)$r['pos'] ?>.</td>
									<td><a class="d-inline-flex align-items-center gap-2 text-reset text-decoration-none" href="<?= plain($r['id'] === $team['id'] ? '#' : md_team_url($r['id'], $season)) ?>"><?php if ($r['logo']): ?><img src="<?= plain(url('api-logo') . '?url=' . rawurlencode($r['logo'])) ?>" alt="" width="26" height="26" loading="lazy"><?php endif; ?><span class="fw-semibold"><?= plain($r['name']) ?></span></a></td>
									<td class="text-end"><?= (int)$r['p'] ?></td><td class="text-end"><?= (int)$r['w'] ?></td><td class="text-end"><?= (int)$r['d'] ?></td><td class="text-end"><?= (int)$r['l'] ?></td>
									<td class="text-end text-nowrap"><?= (int)$r['gf'] ?>:<?= (int)$r['ga'] ?></td>
									<td class="text-end fw-bold"><?= (int)$r['pts'] ?></td>
									<td class="d-none d-md-table-cell"><?= md_form_badges($r['form']) ?></td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>
				<?php endif; ?>
			</div>
			<?php if (!empty($C['official'])): ?>
				<p class="small text-muted-2 mt-3 mb-0"><i class="bi bi-patch-check me-1"></i>Oficiálna tabuľka zo Sportnetu (vrátane kontumácií a odpočtov bodov).</p>
					<?php foreach ($C['table'] as $r) if (!empty($r['note'])): ?><p class="small text-muted-2 mb-0"><i class="bi bi-exclamation-circle me-1"></i><?= plain($r['name']) ?>: <?= plain($r['note']) ?></p><?php endif; ?>
			<?php else: ?>
				<p class="small text-muted-2 mt-3 mb-0"><i class="bi bi-info-circle me-1"></i>Sportnet k tejto súťaži neposlal oficiálnu tabuľku, preto je vypočítaná z výsledkov zápasov (výhra 3 body, remíza 1 bod). Kontumácie a odpočty bodov rozhodnuté mimo ihriska sa v nej nemusia prejaviť.</p>
			<?php endif; ?>
		</div>

		<!-- KOLÁ -->
		<div class="tab-pane fade<?= $tab === 'kola' ? ' show active' : '' ?>" id="t-kola" role="tabpanel">
			<?php if (!$rounds): ?>
				<div class="card-app"><div class="empty-state"><i class="bi bi-calendar-week"></i>Rozpis kôl sa nepodarilo načítať.</div></div>
			<?php else: ?>
				<div class="card-app mb-3"><div class="card-body py-3">
					<div class="round-pager">
						<?php foreach ($rounds as $i => $r): ?>
							<button type="button" class="btn btn-sm <?= $i === $curIdx ? 'btn-primary' : 'btn-outline-secondary' ?>" data-round="<?= $i ?>"><?= (int)$r['no'] ?: plain($r['name']) ?></button>
						<?php endforeach; ?>
					</div>
				</div></div>
				<?php foreach ($rounds as $i => $r): ?>
					<div class="card-app round-card" data-round="<?= $i ?>" <?= $i === $curIdx ? '' : 'hidden' ?>>
						<div class="card-header d-flex flex-wrap gap-2 justify-content-between align-items-center">
							<h2 class="team-title mb-0"><?= plain($r['name']) ?></h2>
							<a class="btn btn-sm btn-primary rounded-pill" href="<?= plain(md_gen_link('round', $tp + ['roundNo' => $r['no']])) ?>"><i class="bi bi-image me-1"></i>Obrázok kola</a>
						</div>
						<ul class="round-list">
							<?php foreach ($r['matches'] as $m): [$dT, $tT] = format_match_date($m['date']); $own = $m['home']['id'] === $team['id'] || $m['away']['id'] === $team['id']; ?>
								<li class="<?= $own ? 'is-own' : '' ?>">
									<span class="when"><?= plain($dT) ?> <?= plain($tT) ?></span>
									<a class="h text-reset text-decoration-none" href="<?= plain(md_team_url($m['home']['id'], $season)) ?>"><?= plain($m['home']['name']) ?></a>
									<a class="sc<?= $m['played'] ? '' : ' upcoming' ?>" href="<?= plain(md_match_url($m['id'], $team['id'])) ?>" title="Zápas"><?= $m['played'] ? (int)$m['home']['score'] . ':' . (int)$m['away']['score'] : plain($tT ?: '–') ?></a>
									<a class="a text-reset text-decoration-none" href="<?= plain(md_team_url($m['away']['id'], $season)) ?>"><?= plain($m['away']['name']) ?></a>
								</li>
							<?php endforeach; ?>
						</ul>
					</div>
				<?php endforeach; ?>
			<?php endif; ?>
		</div>
	</div>
</div>

<script>
$(function () {
	// družstvo a hráči do globálneho hľadania (iba v tomto prehliadači)
	window.MD.rememberTeam && window.MD.rememberTeam(<?= json_encode(['teamId' => $teamId, 'season' => $season, 'cat' => $cat, 'club' => $team['name'], 'logo' => $team['logo'],
		'players' => array_map(fn($p) => ['key' => $p['key'], 'name' => $p['name'], 'nr' => $p['nr'], 'id' => $p['id']], $players)], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?>);
	// karta v adrese (návrat späť ostane na rovnakej karte)
	$('[data-tab]').on('shown.bs.tab', function () {
		const u = new URL(location.href); u.searchParams.set('tab', this.dataset.tab); history.replaceState(null, '', u);
	});
	// kolá
	$('.round-pager').on('click', '[data-round]', function () {
		const i = this.dataset.round;
		$(this).addClass('btn-primary').removeClass('btn-outline-secondary').siblings().removeClass('btn-primary').addClass('btn-outline-secondary');
		$('.round-card').each(function () { this.hidden = this.dataset.round !== i; });
	});
	const $cur = $('.round-pager .btn-primary')[0];
	if ($cur) $cur.scrollIntoView({ block: 'nearest', inline: 'center' });
	// zoradenie tabuľky hráčov
	$('table.sortable').on('click', 'th[data-sort]', function () {
		const $th = $(this), idx = $th.index(), num = $th.data('sort') === 'num';
		const dir = $th.hasClass('asc') ? -1 : 1;
		$th.siblings().removeClass('asc desc'); $th.toggleClass('asc', dir === 1).toggleClass('desc', dir === -1);
		const rows = $th.closest('table').find('tbody tr').get();
		rows.sort((a, b) => {
			const x = $(a).children().eq(idx).data('v'), y = $(b).children().eq(idx).data('v');
			return (num ? (x - y) : String(x).localeCompare(String(y), 'sk')) * (num && idx > 1 ? -dir : dir);
		});
		$th.closest('table').find('tbody').append(rows);
	});
});
</script>

<?php add_layout("footer"); ?>
