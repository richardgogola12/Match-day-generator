<?php
/**
 * Profil hráča: štatistiky sezóny a zoznam zápasov (zo zápisov o stretnutí).
 *   /hrac?team=<ID družstva>&p=<kľúč hráča>&s=<sezóna>&cat=<kategória>
 */
$teamId = (string)($_GET['team'] ?? '');
$key    = (string)($_GET['p'] ?? '');
$season = (string)($_GET['s'] ?? '');
$cat    = (string)($_GET['cat'] ?? '');
if (!preg_match('/^[A-Za-z0-9_\-.]{2,80}$/', $teamId) || !preg_match('/^[a-z0-9\-]{1,80}$/', $key)) show_404();
@set_time_limit(120);

$T = sportnet_team($teamId);
$team = $T['team'] ?? ['id' => '', 'name' => '', 'logo' => ''];
$P = null;
foreach ($T['players'] ?? [] as $p) if ($p['key'] === $key) $P = $p;
if (!$P && empty($T['error'])) show_404();

$q = fn(array $extra = []) => http_build_query(['team' => $teamId, 's' => $season, 'cat' => $cat] + $extra);
$tp = md_team_params($teamId, $team, $cat, $season, $T['competition'] ?? '');
$pp = $P ? $tp + ['player' => $P['key']] : $tp;

// jubileá – najbližší okrúhly počet zápasov / gólov
$mil = fn($n, $steps) => array_values(array_filter($steps, fn($s) => $s >= $n))[0] ?? null;

add_layout("header", array("title" => ($P['name'] ?? 'Hráč') . " – profil | Match Day", "scripts" => ["js/player.js"]));
?>

<section class="hero hero-sm">
	<div class="container-xxl">
		<a href="<?= plain(url('druzstvo') . '?' . $q(['tab' => 'hraci'])) ?>" class="btn btn-sm btn-outline-light rounded-pill mb-3"><i class="bi bi-arrow-left me-1"></i> Hráči družstva</a>
		<?php if ($P): ?>
		<div class="player-hero">
			<div class="player-photo" id="photoBox" data-key="<?= plain('photo:' . $team['id'] . ':' . $P['key']) ?>">
				<span class="player-nr"><?= plain($P['nr'] ?: '?') ?></span>
				<img id="photoImg" alt="" hidden>
			</div>
			<div class="min-w-0">
				<h1 class="mb-1"><?= plain($P['name']) ?></h1>
				<div class="d-flex flex-wrap gap-2">
					<span class="hero-eyebrow"><i class="bi bi-shield"></i> <?= plain($team['name']) ?><?= $cat ? ' · ' . plain($cat) : '' ?></span>
					<?php if ($P['nr'] !== ''): ?><span class="hero-eyebrow"><i class="bi bi-123"></i> Číslo <?= plain($P['nr']) ?></span><?php endif; ?>
					<?php if ($P['gk']): ?><span class="hero-eyebrow"><i class="bi bi-hand-index"></i> Brankár</span><?php endif; ?>
					<?php if ($P['captain']): ?><span class="hero-eyebrow"><i class="bi bi-star-fill text-warning"></i> Kapitán</span><?php endif; ?>
					<?php if ($season): ?><span class="hero-eyebrow"><i class="bi bi-calendar3"></i> <?= plain($season) ?></span><?php endif; ?>
				</div>
			</div>
		</div>
		<?php endif; ?>
	</div>
</section>

<div class="container-xxl overlap">
	<?php if (!$P): ?>
		<div class="card-app"><div class="card-body"><div class="alert alert-danger mb-0"><i class="bi bi-exclamation-octagon me-2"></i><?= plain($T['error'] ?? 'Hráč sa nenašiel.') ?></div></div></div>
	<?php else: ?>
	<div class="row g-4">
		<div class="col-lg-8">
			<div class="row g-3 mb-4">
				<?php
				$tiles = [[$P['apps'], 'zápasov'], [$P['starts'], 'v základe'], [$P['minutes'], 'minút (odhad)'], [$P['goals'], 'gólov']];
				$tiles[] = $P['gk'] ? [$P['cleanSheets'], 'čistých kont'] : [$P['penalties'], 'z penalty'];
				$tiles[] = [$P['yellow'] . ' / ' . $P['red'], 'žlté / červené'];
				foreach ($tiles as [$n, $l]): ?>
					<div class="col-6 col-md-4"><div class="stat-tile"><div class="stat-num"><?= plain($n) ?></div><div class="stat-label"><?= plain($l) ?></div></div></div>
				<?php endforeach; ?>
			</div>

			<div class="card-app overflow-hidden">
				<div class="card-header"><h2 class="team-title mb-0">Zápasy v sezóne</h2></div>
				<?php if (!$P['matches']): ?>
					<div class="empty-state"><i class="bi bi-calendar-x"></i>Zatiaľ žiadne zápasy.</div>
				<?php else: ?>
				<div class="table-responsive">
					<table class="table align-middle mb-0 data-table">
						<thead><tr><th>Dátum</th><th>Súper</th><th class="text-center">Výsledok</th><th>Nastúpil</th><th class="text-end">Min.</th><th class="text-end">G</th><th class="text-end">Karty</th></tr></thead>
						<tbody>
						<?php foreach (array_reverse($P['matches']) as $m): [$dT] = format_match_date($m['date']);
							$rc = ['W' => 'success', 'D' => 'secondary', 'L' => 'danger'][$m['result']] ?? 'light'; ?>
							<tr>
								<td class="text-nowrap small"><?= plain($dT) ?><div class="text-muted-2"><?= plain($m['round']) ?></div></td>
								<td><span class="text-muted-2 small"><?= $m['home'] ? 'doma' : 'vonku' ?></span> <span class="fw-semibold"><?= plain($m['opponent']) ?></span></td>
								<td class="text-center"><?php if ($m['score'] !== ''): ?><span class="badge text-bg-<?= $rc ?>"><?= plain($m['score']) ?></span><?php endif; ?></td>
								<td class="small"><?= ['start' => 'v základe', 'sub' => 'striedal', 'bench' => '<span class="text-muted-2">na lavičke</span>'][$m['role']] ?></td>
								<td class="text-end"><?= (int)$m['minutes'] ?: '–' ?></td>
								<td class="text-end fw-bold"><?= $m['goals'] ? str_repeat('<i class="bi bi-circle-fill text-primary" style="font-size:.55rem"></i>', min(5, $m['goals'])) . ' ' . (int)$m['goals'] : '' ?></td>
								<td class="text-end"><?= str_repeat('<span class="card-ic yellow"></span>', (int)$m['yellow']) . str_repeat('<span class="card-ic red"></span>', (int)$m['red']) ?></td>
							</tr>
						<?php endforeach; ?>
						</tbody>
					</table>
				</div>
				<?php endif; ?>
			</div>
			<p class="small text-muted-2 mt-3 mb-0"><i class="bi bi-info-circle me-1"></i>Údaje sú zo zápisov o stretnutí na Sportnete pre toto družstvo a sezónu. Minúty sú odhad podľa striedaní.</p>
		</div>

		<div class="col-lg-4">
			<div class="card-app mb-4"><div class="card-body">
				<h2 class="h6 section-title text-uppercase text-muted-2 mb-3">Fotka hráča</h2>
				<p class="small text-muted-2">Fotka sa použije v obrázkoch Profil hráča, Hráč zápasu a Jubileum. Uloží sa iba v tomto prehliadači (prenesiete ju zálohou nastavení).</p>
				<label class="bg-drop d-block mb-2" id="photoDrop">
					<i class="bi bi-person-bounding-box fs-3 d-block"></i>
					<span class="fw-semibold">Nahrať fotku</span><br>
					<span class="small">Pretiahnite sem obrázok alebo kliknite. Najlepšie PNG s priehľadným pozadím.</span>
					<input type="file" accept="image/*" id="photoFile" hidden>
				</label>
				<button type="button" class="btn btn-sm btn-outline-danger w-100" id="photoRemove" hidden><i class="bi bi-trash3 me-1"></i>Odstrániť fotku</button>
			</div></div>

			<div class="card-app"><div class="card-body">
				<h2 class="h6 section-title text-uppercase text-muted-2 mb-3">Obrázky hráča</h2>
				<div class="d-grid gap-2">
					<a class="btn btn-primary" href="<?= plain(md_gen_link('player', $pp)) ?>"><i class="bi bi-person-vcard me-1"></i>Profil hráča</a>
					<?php
					$lastM = null;
					foreach (array_reverse($P['matches']) as $m) if ($m['role'] !== 'bench' && $m['score'] !== '') { $lastM = $m; break; }
					if ($lastM): ?>
						<a class="btn btn-outline-primary" href="<?= plain(md_gen_link('motm', $pp + ['matchRef' => $lastM['id']])) ?>"><i class="bi bi-award me-1"></i>Hráč zápasu <span class="small text-muted-2">(<?= plain($lastM['opponent']) ?>)</span></a>
					<?php endif; ?>
					<?php
					$next = $mil($P['apps'], [10, 25, 50, 75, 100, 150, 200, 250, 300, 400, 500]);
					?>
					<a class="btn btn-outline-primary" href="<?= plain(md_gen_link('milestone', $pp + ['mvalue' => $P['apps'], 'mkind' => 'apps'])) ?>"><i class="bi bi-trophy me-1"></i>Jubileum – <?= (int)$P['apps'] ?>. zápas</a>
					<?php if ($P['goals'] > 0): ?>
						<a class="btn btn-outline-primary" href="<?= plain(md_gen_link('milestone', $pp + ['mvalue' => $P['goals'], 'mkind' => 'goals'])) ?>"><i class="bi bi-bullseye me-1"></i>Jubileum – <?= (int)$P['goals'] ?>. gól</a>
					<?php endif; ?>
				</div>
				<div class="form-text mt-2">Počet pri jubileu (napr. 100. zápas za klub) zmeníte v generátore.<?= $next ? ' Najbližšie okrúhle jubileum v tejto sezóne: ' . $next . '. zápas.' : '' ?></div>
			</div></div>
		</div>
	</div>
	<?php endif; ?>
</div>

<?php add_layout("footer"); ?>
