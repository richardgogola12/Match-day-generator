<?php
/**
 * Kontrola Sportnetu (iba správca): /match-day/kontrola[?q=<klub>]
 * Postupne overí všetky zdroje dát, ktoré aplikácia používa – bez cache, so skutočným klubom.
 * Pomôže, keď Sportnet zmení formát dát alebo prestane odpovedať.
 */
if (!is_admin()) redirect('spravca?dalej=kontrola', 303);
no_store_headers();
@set_time_limit(120);

$notice = '';
if (is_post_request() && csrf_ok($_POST['csrf'] ?? null) && ($_POST['action'] ?? '') === 'clear-cache') {
    $n = 0;
    foreach ([md_cache_dir('sportnet'), md_cache_dir('logos')] as $d) {
        foreach (glob($d . '/*') ?: [] as $f) if (is_file($f) && basename($f) !== '.htaccess' && @unlink($f)) $n++;
    }
    $notice = 'Vymazaných ' . $n . ' súborov z cache. Ďalšie načítanie stránok stiahne čerstvé dáta zo Sportnetu.';
}

$q = trim((string)($_GET['q'] ?? 'nitra'));
$q = mb_substr($q, 0, 40, 'UTF-8');
$run = isset($_GET['q']);

require_once INCLUDES_DIR . 'check.php';
$checks = $run ? md_sportnet_checks($q) : [];

$cnt = ['ok' => 0, 'warn' => 0, 'fail' => 0, 'skip' => 0];
foreach ($checks as $c) $cnt[$c['state']]++;
$cacheFiles = count(glob(md_cache_dir('sportnet') . '/*') ?: []) + count(glob(md_cache_dir('logos') . '/*') ?: []);
$blocked = md_json_read(md_cache_dir() . '/blocked-hosts.json');
arsort($blocked);

add_layout("header", array("title" => "Kontrola Sportnetu | Match Day", "noindex" => true));
?>

<section class="hero hero-sm">
	<div class="container-xxl">
		<a href="<?= url('spravca') ?>" class="btn btn-sm btn-outline-light rounded-pill mb-3"><i class="bi bi-arrow-left me-1"></i> Správca</a>
		<h1 class="mb-2">Kontrola <span class="accent">Sportnetu</span></h1>
		<p class="lead mb-0">Overí všetky zdroje dát na skutočnom klube – bez cache.</p>
	</div>
</section>

<div class="container-xxl overlap">
	<?php if ($notice): ?><div class="alert alert-success"><i class="bi bi-check-circle me-1"></i><?= plain($notice) ?></div><?php endif; ?>
	<div class="card-app mb-4"><div class="card-body d-flex flex-wrap gap-3 align-items-end justify-content-between">
		<form class="d-flex flex-wrap gap-2 align-items-end" method="get" action="<?= url('kontrola') ?>">
			<div><label class="form-label small fw-semibold mb-1" for="chkQ">Klub na test</label>
				<input class="form-control" id="chkQ" name="q" value="<?= plain($q) ?>" placeholder="napr. nitra" required></div>
			<button class="btn btn-primary" type="submit"><i class="bi bi-play-fill me-1"></i>Spustiť kontrolu</button>
		</form>
		<form method="post" action="<?= url('kontrola') . ($run ? '?q=' . rawurlencode($q) : '') ?>">
			<input type="hidden" name="csrf" value="<?= plain(csrf_token()) ?>"><input type="hidden" name="action" value="clear-cache">
			<button class="btn btn-outline-secondary" type="submit" title="Uložené odpovede Sportnetu a logá"><i class="bi bi-trash3 me-1"></i>Vymazať cache (<?= $cacheFiles ?> súborov)</button>
		</form>
	</div></div>

	<?php if ($run): ?>
	<div class="d-flex flex-wrap gap-2 mb-3">
		<span class="badge text-bg-success fs-6"><?= $cnt['ok'] ?> v poriadku</span>
		<?php if ($cnt['warn']): ?><span class="badge text-bg-warning fs-6"><?= $cnt['warn'] ?> upozornenia</span><?php endif; ?>
		<?php if ($cnt['fail']): ?><span class="badge text-bg-danger fs-6"><?= $cnt['fail'] ?> chyby</span><?php endif; ?>
		<?php if ($cnt['skip']): ?><span class="badge text-bg-secondary fs-6"><?= $cnt['skip'] ?> preskočené</span><?php endif; ?>
	</div>
	<div class="card-app overflow-hidden">
		<div class="table-responsive">
			<table class="table align-middle mb-0 data-table">
				<thead><tr><th></th><th>Zdroj</th><th>Výsledok</th><th class="text-end">Čas</th></tr></thead>
				<tbody>
				<?php foreach ($checks as $c): $ic = ['ok' => 'bi-check-circle-fill text-success', 'warn' => 'bi-exclamation-triangle-fill text-warning', 'fail' => 'bi-x-octagon-fill text-danger', 'skip' => 'bi-dash-circle text-muted-2'][$c['state']]; ?>
					<tr>
						<td class="fs-5"><i class="bi <?= $ic ?>"></i></td>
						<td><div class="fw-semibold"><?= plain($c['name']) ?></div><div class="small text-muted-2"><?= plain($c['what']) ?></div></td>
						<td class="small"><?= plain($c['msg']) ?><?php if ($c['url']): ?><div class="text-muted-2 text-break" style="font-size:.72rem"><?= plain(preg_replace('#^https?://#', '', $c['url'])) ?><?= $c['code'] !== '' ? ' · HTTP ' . (int)$c['code'] : '' ?></div><?php endif; ?></td>
						<td class="text-end small text-nowrap"><?= $c['ms'] !== '' ? (int)$c['ms'] . ' ms' : '' ?></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		</div>
	</div>
	<?php endif; ?>
	<?php if ($blocked): ?>
	<div class="card-app mt-4"><div class="card-body">
		<h2 class="h6 section-title text-uppercase text-muted-2 mb-2"><i class="bi bi-shield-x me-1"></i>Zablokované domény obrázkov</h2>
		<p class="small text-muted-2 mb-2">Z týchto domén server odmietol stiahnuť logo alebo fotku. Ak ide o Sportnet, pridajte doménu do <code>IMAGE_HOSTS</code> v <code>config.local.php</code>. Povolené: <code><?= plain(implode(', ', md_image_hosts())) ?></code>.</p>
		<ul class="small mb-0"><?php foreach (array_slice($blocked, 0, 20, true) as $h => $i): ?><li><code><?= plain($h) ?></code> – <?= (int)$i['n'] ?>× (naposledy <?= plain(date('j. n. H:i', strtotime($i['last']))) ?>)</li><?php endforeach; ?></ul>
	</div></div>
	<?php endif; ?>
	<?php if ($run): ?>
	<p class="small text-muted-2 mt-3 mb-0"><i class="bi bi-info-circle me-1"></i>Upozornenie (žlté) znamená, že zdroj odpovedá, ale pre testovaný klub nemá dáta – vyskúšajte iný klub. Chyba (červená) znamená, že Sportnet neodpovedá alebo zmenil formát dát.</p>
	<?php else: ?>
		<div class="card-app"><div class="empty-state"><i class="bi bi-activity"></i>Zadajte klub a spustite kontrolu.</div></div>
	<?php endif; ?>
</div>

<?php add_layout("footer"); ?>
