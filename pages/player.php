<?php
/**
 * Profil hráča: fotka, štatistiky sezóny v družstve, zápasy, kariéra.
 *   /hrac?id=<Sportnet ID>                              (družstvo a sezóna sa zistia z posledných zápasov)
 *   /hrac?team=<ID družstva>&p=<kľúč hráča>&s=<sezóna>&cat=<kategória>[&id=…]
 */
$sid    = (string)($_GET['id'] ?? '');
$teamId = (string)($_GET['team'] ?? '');
$key    = (string)($_GET['p'] ?? '');
$season = (string)($_GET['s'] ?? '');
$cat    = (string)($_GET['cat'] ?? '');
if ($sid !== '' && !preg_match('/^[A-Za-z0-9_\-]{6,64}$/', $sid)) show_404();
if ($teamId !== '' && !preg_match('/^[A-Za-z0-9_\-.]{2,80}$/', $teamId)) show_404();
if ($key !== '' && !preg_match('/^[a-z0-9\-]{1,80}$/', $key)) show_404();
if ($sid === '' && ($teamId === '' || $key === '')) show_404();
@set_time_limit(120);

$U = $sid !== '' ? sportnet_user($sid) : null;
$recent = [];
if ($U) $recent = sportnet_player_recent($sid, $U['clubs'], 6);
// bez družstva: posledné družstvo, za ktoré hráč hral
if ($teamId === '' && $recent) {
    $teamId = $recent[0]['teamId']; $season = $recent[0]['season']; $cat = $recent[0]['cat'];
}
if ($sid !== '' && !$U && $teamId === '') show_404();

$T = $teamId !== '' ? sportnet_team($teamId) : [];
$team = ($T['team'] ?? []) + ['id' => '', 'name' => '', 'logo' => '', 'club' => ''];
$P = null;
foreach ($T['players'] ?? [] as $p) if (($sid !== '' && $p['id'] === $sid) || ($sid === '' && $p['key'] === $key)) $P = $p;
if (!$P && $key !== '') foreach ($T['players'] ?? [] as $p) if ($p['key'] === $key) $P = $p;
if (!$P && !$U) show_404();
if ($sid === '' && $P && $P['id'] !== '') { $sid = $P['id']; $U = sportnet_user($sid); if ($U) $recent = sportnet_player_recent($sid, $U['clubs'], 6); }
$career = $sid !== '' ? sportnet_player_career($sid) : null;

$name = $P['name'] ?? ($U['name'] ?? 'Hráč');
$pkey = $P['key'] ?? sn_name_key($name);
$tp = md_team_params($teamId, $team, $cat, $season, $T['competition'] ?? '');
$pp = $tp + ['player' => $pkey];
$remotePhoto = $U && $U['photo'] ? $U['photo'] : ($P['photo'] ?? '');

// jubileá – najbližší okrúhly počet zápasov
$mil = fn($n, $steps) => array_values(array_filter($steps, fn($s) => $s >= $n))[0] ?? null;

add_layout("header", array("title" => $name . " – profil | Match Day", "scripts" => ["js/player.js"]));
?>

<section class="hero hero-sm">
	<div class="container-xxl">
		<div class="player-hero">
			<div class="player-photo" id="photoBox" data-key="<?= plain('photo:' . ($team['id'] ?: $teamId) . ':' . $pkey) ?>" data-remote="<?= plain($remotePhoto ? url('api-logo') . '?raw=1&url=' . rawurlencode($remotePhoto) : '') ?>">
				<span class="player-nr"><?= plain(($P['nr'] ?? '') ?: '?') ?></span>
				<img id="photoImg" alt="Fotka – <?= plain($name) ?>" hidden>
			</div>
			<div class="min-w-0">
				<h1 class="mb-2"><?= plain($name) ?></h1>
				<div class="d-flex flex-wrap gap-2">
					<?php if ($team['name']): ?>
						<a class="hero-eyebrow hero-link" href="<?= plain(md_team_url($teamId, $season, $cat)) ?>"><i class="bi bi-shield"></i> <?= plain($team['name']) ?><?= $cat ? ' · ' . plain($cat) : '' ?></a>
					<?php endif; ?>
					<?php if (($P['nr'] ?? '') !== ''): ?><span class="hero-eyebrow"><i class="bi bi-123"></i> Číslo <?= plain($P['nr']) ?></span><?php endif; ?>
					<?php if (!empty($P['gk'])): ?><span class="hero-eyebrow"><i class="bi bi-hand-index"></i> Brankár</span><?php endif; ?>
					<?php if (!empty($P['captain'])): ?><span class="hero-eyebrow"><i class="bi bi-star-fill text-warning"></i> Kapitán</span><?php endif; ?>
					<?php if (!empty($U['birthyear'])): ?><span class="hero-eyebrow"><i class="bi bi-cake2"></i> nar. <?= (int)$U['birthyear'] ?><?= $U['age'] ? ' (' . (int)$U['age'] . ' r.)' : '' ?></span><?php endif; ?>
					<?php if ($season): ?><span class="hero-eyebrow"><i class="bi bi-calendar3"></i> <?= plain($season) ?></span><?php endif; ?>
				</div>
			</div>
		</div>
	</div>
</section>

<div class="container-xxl overlap">
	<div class="row g-4">
		<div class="col-lg-8">
			<?php if ($P): ?>
			<div class="row g-3 mb-4">
				<?php
				$tiles = [[$P['apps'], 'odohraných zápasov'], [$P['starts'], 'v základnej zostave'], [$P['minutes'] !== null ? number_format($P['minutes'], 0, ',', ' ') : '–', 'odohraných minút'],
				          [$P['goals'], 'gólov' . ($P['penalties'] ? ' (' . $P['penalties'] . ' z PK)' : '')], [$P['yellow'], 'žltých kariet'], [$P['red'], 'červených kariet']];
				foreach ($tiles as [$n, $l]): ?>
					<div class="col-6 col-md-4"><div class="stat-tile"><div class="stat-num"><?= plain($n) ?></div><div class="stat-label"><?= plain($l) ?></div></div></div>
				<?php endforeach; ?>
			</div>

			<div class="card-app overflow-hidden">
				<div class="card-header d-flex flex-wrap gap-2 justify-content-between align-items-center">
					<h2 class="team-title mb-0">Zápasy v sezóne <?= plain($season) ?></h2>
					<?php if ($team['name']): ?><a class="small" href="<?= plain(md_team_url($teamId, $season, $cat, ['tab' => 'hraci'])) ?>">Všetci hráči družstva <i class="bi bi-arrow-right"></i></a><?php endif; ?>
				</div>
				<?php if (!$P['matches']): ?>
					<div class="empty-state"><i class="bi bi-calendar-x"></i>Zatiaľ žiadne zápasy.</div>
				<?php else: ?>
				<div class="table-responsive">
					<table class="table table-hover align-middle mb-0 data-table">
						<thead><tr><th>Dátum</th><th>Súper</th><th class="text-center">Výsledok</th><th>Nastúpil</th><th class="text-end">Min</th><th class="text-end">G</th><th class="text-end">Karty</th></tr></thead>
						<tbody>
						<?php foreach (array_reverse($P['matches']) as $m): [$dT] = format_match_date($m['date']);
							$rc = ['W' => 'success', 'D' => 'secondary', 'L' => 'danger'][$m['result']] ?? 'light';
							$mu = md_match_url($m['id'], $team['id']); ?>
							<tr>
								<td class="text-nowrap small"><a class="text-reset text-decoration-none" href="<?= plain($mu) ?>"><?= plain($dT) ?><div class="text-muted-2"><?= plain($m['round']) ?></div></a></td>
								<td><span class="text-muted-2 small"><?= $m['home'] ? 'doma' : 'vonku' ?></span> <a class="fw-semibold text-reset" href="<?= plain($mu) ?>"><?= plain($m['opponent']) ?></a></td>
								<td class="text-center"><?php if ($m['score'] !== ''): ?><a class="badge text-bg-<?= $rc ?> text-decoration-none" href="<?= plain($mu) ?>"><?= plain($m['score']) ?></a><?php endif; ?></td>
								<td class="small"><?php
									$role = $m['role'] ?? 'listed';
									if ($role === 'start') echo 'základ' . (($m['out'] ?? null) !== null ? ' <span class="text-muted-2">→ ' . (int)$m['out'] . "'</span>" : '');
									elseif ($role === 'sub') echo 'striedal <span class="text-muted-2">' . (int)$m['in'] . "'</span>";
									elseif ($role === 'bench') echo '<span class="text-muted-2">lavička</span>';
									else echo '<span class="text-muted-2">v zápise</span>';
								?></td>
								<td class="text-end small"><?= isset($m['min']) && $m['min'] !== null ? (int)$m['min'] . "'" : '<span class="text-muted-2">–</span>' ?></td>
								<td class="text-end fw-bold"><?= $m['goals'] ? str_repeat('<i class="bi bi-circle-fill text-primary" style="font-size:.55rem"></i>', min(5, $m['goals'])) . ' ' . (int)$m['goals'] : '' ?></td>
								<td class="text-end"><?= str_repeat('<span class="card-ic yellow"></span>', (int)$m['yellow']) . str_repeat('<span class="card-ic red"></span>', (int)$m['red']) ?></td>
							</tr>
						<?php endforeach; ?>
						</tbody>
					</table>
				</div>
				<?php endif; ?>
			</div>
			<p class="small text-muted-2 mt-3 mb-0"><i class="bi bi-info-circle me-1"></i>Súčty minút, zápasov a štartov sú oficiálne štatistiky Sportnetu pre toto družstvo a sezónu. Minúty pri jednotlivých zápasoch sú vypočítané zo striedaní a červených kariet v zápise o stretnutí.</p>
			<?php else: ?>
				<div class="card-app mb-4"><div class="card-body"><div class="alert alert-info mb-0"><i class="bi bi-info-circle me-2"></i>Hráč zatiaľ nemá zápasy za <?= $team['name'] ? 'družstvo ' . plain($team['name'] . ($cat ? ' ' . $cat : '')) : 'žiadne družstvo' ?> v tejto sezóne.</div></div></div>
			<?php endif; ?>

			<?php if ($recent): ?>
			<div class="card-app overflow-hidden mt-4">
				<div class="card-header"><h2 class="team-title mb-0">Posledné zápasy (všetky družstvá)</h2></div>
				<ul class="mini-matches">
					<?php foreach (array_slice($recent, 0, 8) as $r): $b = $r['brief']; [$dT] = format_match_date($b['date']); ?>
						<li>
							<a href="<?= plain(md_match_url($b['id'], $r['teamId'])) ?>">
								<span class="when"><?= plain($dT) ?><small><?= plain($r['cat']) ?></small></span>
								<span class="h"><?= plain($b['home']['name']) ?></span>
								<span class="sc"><?= $b['played'] ? (int)$b['home']['score'] . ':' . (int)$b['away']['score'] : '–' ?></span>
								<span class="a"><?= plain($b['away']['name']) ?></span>
							</a>
						</li>
					<?php endforeach; ?>
				</ul>
			</div>
			<?php endif; ?>
		</div>

		<div class="col-lg-4">
			<?php if ($career): ?>
			<div class="card-app mb-4"><div class="card-body">
				<h2 class="h6 section-title text-uppercase text-muted-2 mb-3">Kariéra <span class="text-lowercase fw-normal">(všetky súťaže)</span></h2>
				<dl class="kv mb-0">
					<dt>Zápasy</dt><dd><?= (int)$career['apps'] ?> <span class="fw-normal text-muted-2">(<?= (int)$career['starts'] ?> v základe)</span></dd>
					<dt>Minúty</dt><dd><?= number_format($career['minutes'], 0, ',', ' ') ?></dd>
					<dt>Góly</dt><dd><?= (int)$career['goals'] ?></dd>
					<dt>Žlté / červené karty</dt><dd><?= (int)$career['yellow'] ?> / <?= (int)$career['red'] ?></dd>
				</dl>
				<?php if ($teamId !== ''): ?><a class="btn btn-sm btn-outline-primary rounded-pill mt-3" href="<?= plain(md_gen_link('milestone', $pp + ['mvalue' => $career['apps'], 'mkind' => 'apps'])) ?>"><i class="bi bi-trophy me-1"></i>Jubileum – <?= (int)$career['apps'] ?>. zápas v kariére</a><?php endif; ?>
			</div></div>
			<?php endif; ?>

			<?php if ($U && $U['clubs']): ?>
			<div class="card-app mb-4"><div class="card-body">
				<h2 class="h6 section-title text-uppercase text-muted-2 mb-3">Kluby</h2>
				<div class="d-flex flex-wrap gap-2">
					<?php
					// názvy klubov z posledných zápasov (inak z adresy klubu)
					$clubNames = [];
					foreach ($recent as $r) foreach (['home', 'away'] as $sd) if (($r['brief'][$sd]['club'] ?? '') !== '') $clubNames[$r['brief'][$sd]['club']] = $r['brief'][$sd]['name'];
					foreach ($U['clubs'] as $c): ?>
						<a class="recent-chip" href="<?= plain(md_club_url($c)) ?>"><i class="bi bi-shield-shaded text-primary"></i><span class="label"><?= plain($clubNames[$c] ?? mb_convert_case(str_replace(['issf_club_', '-'], ['klub ', ' '], sn_club_slug($c)), MB_CASE_TITLE, 'UTF-8')) ?></span></a>
					<?php endforeach; ?>
				</div>
			</div></div>
			<?php endif; ?>

			<?php if ($teamId !== ''): ?>
			<div class="card-app mb-4"><div class="card-body">
				<h2 class="h6 section-title text-uppercase text-muted-2 mb-3">Obrázky hráča</h2>
				<div class="d-grid gap-2">
					<a class="btn btn-primary" href="<?= plain(md_gen_link('player', $pp)) ?>"><i class="bi bi-person-vcard me-1"></i>Profil hráča</a>
					<?php
					$lastM = null;
					foreach (array_reverse($P['matches'] ?? []) as $m) if ($m['score'] !== '') { $lastM = $m; break; }
					if ($lastM): ?>
						<a class="btn btn-outline-primary" href="<?= plain(md_gen_link('motm', $pp + ['matchRef' => $lastM['id']])) ?>"><i class="bi bi-award me-1"></i>Hráč zápasu <span class="small text-muted-2">(<?= plain($lastM['opponent']) ?>)</span></a>
					<?php endif; ?>
					<?php if ($P): $next = $mil($P['apps'], [10, 25, 50, 75, 100, 150, 200, 250, 300, 400, 500]); ?>
					<a class="btn btn-outline-primary" href="<?= plain(md_gen_link('milestone', $pp + ['mvalue' => $P['apps'], 'mkind' => 'apps'])) ?>"><i class="bi bi-trophy me-1"></i>Jubileum – <?= (int)$P['apps'] ?>. zápas</a>
					<?php if ($P['goals'] > 0): ?>
						<a class="btn btn-outline-primary" href="<?= plain(md_gen_link('milestone', $pp + ['mvalue' => $P['goals'], 'mkind' => 'goals'])) ?>"><i class="bi bi-bullseye me-1"></i>Jubileum – <?= (int)$P['goals'] ?>. gól</a>
					<?php endif; ?>
					<?php $bdAge = !empty($U['birthyear']) ? (int)date('Y') - (int)$U['birthyear'] : 0; ?>
					<a class="btn btn-outline-primary" href="<?= plain(md_gen_link('birthday', $pp + ($bdAge ? ['age' => $bdAge] : []))) ?>"><i class="bi bi-cake2 me-1"></i>Narodeniny<?= $bdAge ? ' – ' . $bdAge . '.' : '' ?></a>
					<?php endif; ?>
				</div>
				<?php if (!empty($next)): ?><div class="form-text mt-2">Najbližšie okrúhle jubileum v tejto sezóne: <?= (int)$next ?>. zápas.</div><?php endif; ?>
			</div></div>
			<?php endif; ?>

			<div class="card-app"><div class="card-body">
				<h2 class="h6 section-title text-uppercase text-muted-2 mb-3">Fotka hráča</h2>
				<p class="small text-muted-2"><?= $remotePhoto ? 'Fotka je zo Sportnetu. Vlastnou fotkou ju nahradíte' : 'Sportnet fotku hráča nemá. Nahrajte vlastnú' ?> – použije sa v obrázkoch Profil hráča, Hráč zápasu a Jubileum. Vlastná fotka sa uloží iba v tomto prehliadači.</p>
				<label class="bg-drop d-block mb-2" id="photoDrop">
					<i class="bi bi-person-bounding-box fs-3 d-block"></i>
					<span class="fw-semibold">Nahrať vlastnú fotku</span><br>
					<span class="small">Pretiahnite sem obrázok alebo kliknite. Najlepšie PNG s priehľadným pozadím.</span>
					<input type="file" accept="image/*" id="photoFile" hidden>
				</label>
				<button type="button" class="btn btn-sm btn-outline-danger w-100" id="photoRemove" hidden><i class="bi bi-trash3 me-1"></i>Odstrániť vlastnú fotku</button>
			</div></div>
		</div>
	</div>
</div>

<?php add_layout("footer"); ?>
