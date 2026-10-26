<?php
/**
 * Celý zápis o stretnutí: výsledok, polčas, góly, karty, striedania, zostavy, rozhodcovia, diváci.
 *   /zapas?matchID=…&team1ID=…&team2ID=…&d1name=…&d2name=…&… (rovnaké parametre ako generátor)
 */
$b = md_brief_from_get();
if (!preg_match('/^[A-Za-z0-9_\-]{6,64}$/', $b['id'])) show_404();
$data = sn_fetch_cached(sprintf(API_MATCH, rawurlencode($b['id'])), 300);
$err = !is_array($data) || !empty($data['error']);
$events = $err ? [] : sportnet_events($data, $b['home']['id']);
$lineups = $err ? null : sportnet_lineups($data, $b['home']['id'], $b['away']['id']);
$rep = $err ? [] : sportnet_report($data);
$len = $err ? 90 : sn_match_length($data);
$mins = [];
if ($lineups) foreach (['home', 'away'] as $s) $mins[$s] = sn_match_minutes($lineups[$s], $events, $b[$s]['id'], $len);
[$dT, $tT] = format_match_date($b['date']);
$params = $_GET;
$myTeam = (string)($_GET['myTeam'] ?? '');

$icon = ['goal' => '<i class="bi bi-circle-fill ev-goal"></i>', 'yellow' => '<span class="card-ic yellow"></span>', 'red' => '<span class="card-ic red"></span>', 'sub' => '<i class="bi bi-arrow-left-right ev-sub"></i>'];

add_layout("header", array("title" => $b['home']['name'] . ' – ' . $b['away']['name'] . " | zápis | Match Day"));
?>

<section class="hero hero-sm">
	<div class="container-xxl">
		<button type="button" onclick="history.length > 1 ? history.back() : location.href='<?= url('/') ?>'" class="btn btn-sm btn-outline-light rounded-pill mb-3"><i class="bi bi-arrow-left me-1"></i> Späť</button>
		<div class="d-flex flex-wrap gap-2 mb-2">
			<?php if ($b['round']): ?><span class="hero-eyebrow"><i class="bi bi-flag"></i> <?= plain($b['round']) ?></span><?php endif; ?>
			<?php if ($dT): ?><span class="hero-eyebrow"><i class="bi bi-calendar3"></i> <?= plain($dT) ?> · <?= plain($tT) ?></span><?php endif; ?>
			<?php if ($b['competition']): ?><span class="hero-eyebrow"><i class="bi bi-trophy"></i> <?= plain($b['competition']) ?></span><?php endif; ?>
		</div>
		<div class="scoreboard">
			<div class="sb-team">
				<?php if ($b['home']['logo']): ?><img src="<?= plain(url('api-logo') . '?url=' . rawurlencode($b['home']['logo'])) ?>" alt="" width="72" height="72"><?php endif; ?>
				<span><?= plain($b['home']['name']) ?></span>
			</div>
			<div class="sb-score">
				<?= $b['played'] ? plain($b['home']['score']) . '<span>:</span>' . plain($b['away']['score']) : '<small>' . plain($tT ?: 'vs') . '</small>' ?>
				<?php if (!empty($rep['halftime'])): ?><div class="sb-half">polčas <?= plain($rep['halftime']) ?></div><?php endif; ?>
			</div>
			<div class="sb-team">
				<?php if ($b['away']['logo']): ?><img src="<?= plain(url('api-logo') . '?url=' . rawurlencode($b['away']['logo'])) ?>" alt="" width="72" height="72"><?php endif; ?>
				<span><?= plain($b['away']['name']) ?></span>
			</div>
		</div>
	</div>
</section>

<div class="container-xxl overlap">
	<?php if ($err): ?>
		<div class="card-app mb-4"><div class="card-body"><div class="alert alert-warning mb-0"><i class="bi bi-exclamation-triangle me-2"></i>Zápis o stretnutí sa nepodarilo načítať zo Sportnetu.</div></div></div>
	<?php endif; ?>

	<div class="card-app mb-4"><div class="card-body py-3 d-flex flex-wrap gap-2">
		<span class="small fw-semibold text-muted-2 align-self-center me-1">Vytvoriť obrázok:</span>
		<a class="btn btn-sm btn-outline-primary" href="<?= plain(md_gen_link('matchday', $params)) ?>"><i class="bi bi-calendar-event me-1"></i>Match day</a>
		<a class="btn btn-sm btn-outline-primary" href="<?= plain(md_gen_link('lineup', $params)) ?>"><i class="bi bi-person-lines-fill me-1"></i>Zostava</a>
		<?php if ($b['played']): ?>
			<a class="btn btn-sm btn-outline-primary" href="<?= plain(md_gen_link('result', $params)) ?>"><i class="bi bi-trophy me-1"></i>Výsledok</a>
			<a class="btn btn-sm btn-outline-primary" href="<?= plain(md_gen_link('goals', $params)) ?>"><i class="bi bi-list-ol me-1"></i>Strelci</a>
			<a class="btn btn-sm btn-primary" href="<?= plain(md_gen_link('report', $params)) ?>"><i class="bi bi-file-text me-1"></i>Súhrn zápasu</a>
		<?php endif; ?>
	</div></div>

	<div class="row g-4">
		<div class="col-lg-7">
			<div class="card-app h-100">
				<div class="card-header"><h2 class="team-title mb-0">Priebeh zápasu</h2></div>
				<?php if (!$events): ?>
					<div class="empty-state"><i class="bi bi-clock-history"></i><?= $b['played'] ? 'Zápis neobsahuje udalosti.' : 'Zápas sa ešte nehral.' ?></div>
				<?php else: ?>
				<ul class="timeline">
					<?php foreach ($events as $e): $right = $e['kind'] === 'goal' ? $e['side'] === 'away' : $e['side'] === 'away'; ?>
						<li class="<?= $right ? 'away' : 'home' ?> ev-<?= $e['kind'] ?>">
							<span class="min"><?= (int)$e['minute'] ?>'</span>
							<span class="ic"><?= $icon[$e['kind']] ?></span>
							<span class="tx">
								<?php if ($e['kind'] === 'sub'): ?>
									<span class="text-success"><i class="bi bi-arrow-up-short"></i><?= plain($e['in']) ?></span> <span class="text-danger"><i class="bi bi-arrow-down-short"></i><?= plain($e['name']) ?></span>
								<?php else: ?>
									<strong><?= plain($e['name']) ?></strong><?= !empty($e['own']) ? ' <span class="text-muted-2">(vlastný gól)</span>' : '' ?><?= !empty($e['penalty']) ? ' <span class="text-muted-2">(pokutový kop)</span>' : '' ?>
								<?php endif; ?>
							</span>
						</li>
					<?php endforeach; ?>
				</ul>
				<?php endif; ?>
			</div>
		</div>
		<div class="col-lg-5">
			<div class="card-app mb-4"><div class="card-body">
				<h2 class="h6 section-title text-uppercase text-muted-2 mb-3">Informácie</h2>
				<dl class="kv mb-0">
					<?php if (!empty($rep['venue'])): ?><dt>Štadión</dt><dd><?= plain($rep['venue']) ?></dd><?php endif; ?>
					<?php if (($rep['spectators'] ?? '') !== ''): ?><dt>Diváci</dt><dd><?= plain($rep['spectators']) ?></dd><?php endif; ?>
					<?php if (!empty($rep['halftime'])): ?><dt>Polčas</dt><dd><?= plain($rep['halftime']) ?></dd><?php endif; ?>
					<?php foreach ($rep['officials'] ?? [] as $o): ?><dt><?= plain($o['role'] ?: 'Funkcionár') ?></dt><dd><?= plain($o['name']) ?></dd><?php endforeach; ?>
					<?php
					$cnt = fn($k, $s) => count(array_filter($events, fn($e) => $e['kind'] === $k && $e['side'] === $s));
					if ($events): ?>
						<dt>Žlté karty</dt><dd><?= $cnt('yellow', 'home') ?> : <?= $cnt('yellow', 'away') ?></dd>
						<dt>Červené karty</dt><dd><?= $cnt('red', 'home') ?> : <?= $cnt('red', 'away') ?></dd>
					<?php endif; ?>
				</dl>
			</div></div>
		</div>
	</div>

	<?php if ($lineups && ($lineups['home']['start'] || $lineups['away']['start'])): ?>
	<div class="row g-4 mt-0">
		<?php foreach (['home', 'away'] as $s): $L = $lineups[$s]; ?>
			<div class="col-md-6">
				<div class="card-app h-100">
					<div class="card-header"><h2 class="team-title mb-0"><?= plain($b[$s]['name']) ?></h2></div>
					<div class="card-body">
						<?php foreach (['start' => 'Základná zostava', 'subs' => 'Náhradníci'] as $k => $lab): if (!$L[$k]) continue; ?>
							<h3 class="h6 section-title text-uppercase text-muted-2 mb-2 <?= $k === 'subs' ? 'mt-3' : '' ?>"><?= $lab ?></h3>
							<ol class="players-mini" style="columns:1">
								<?php foreach ($L[$k] as $p): ?>
									<li><span class="nr"><?= plain($p['nr'] ?: '–') ?></span><?= plain($p['name']) ?><?= $p['captain'] ? ' <span class="badge text-bg-warning">C</span>' : '' ?><?= $p['gk'] ? ' <span class="badge text-bg-secondary">B</span>' : '' ?></li>
								<?php endforeach; ?>
							</ol>
						<?php endforeach; ?>
						<?php if ($L['coach']): ?><div class="small mt-3"><span class="text-muted-2">Tréner:</span> <strong><?= plain($L['coach']) ?></strong></div><?php endif; ?>
					</div>
				</div>
			</div>
		<?php endforeach; ?>
	</div>
	<?php endif; ?>
</div>

<?php add_layout("footer"); ?>
