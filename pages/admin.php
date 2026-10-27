<?php
/**
 * Prihlásenie a prehľad správcu.
 * Heslo sa posiela iba POST formulárom – nikdy nie je v adrese, takže sa neobjaví
 * v logoch servera, histórii prehliadača ani v offline cache PWA.
 */
no_store_headers();

$error = '';
if (is_post_request()) {
    $action = $_POST['action'] ?? '';
    if (!csrf_ok($_POST['csrf'] ?? null)) {
        $error = 'Formulár vypršal, skúste to znova.';
    } elseif ($action === 'logout') {
        admin_logout();
        redirect('spravca?odhlaseny=1', 303);
    } elseif ($action === 'login') {
        if (admin_login($_POST['password'] ?? '')) {
            $next = $_POST['next'] ?? '';
            redirect(in_array($next, ['galeria?vsetky=1', 'log', 'kontrola'], true) ? $next : 'spravca', 303);
        }
        $lock = admin_lock_minutes();
        $error = $lock ? 'Príliš veľa neúspešných pokusov. Skúste to o ' . $lock . ' min.' : 'Nesprávne heslo.';
    } elseif ($action === 'password' && is_admin()) {
        $new = (string)($_POST['new'] ?? '');
        if (!md_admin_verify($_POST['current'] ?? '')) $pwMsg = ['danger', 'Súčasné heslo nie je správne.'];
        elseif (mb_strlen($new) < 8) $pwMsg = ['danger', 'Nové heslo musí mať aspoň 8 znakov.'];
        elseif ($new !== (string)($_POST['new2'] ?? '')) $pwMsg = ['danger', 'Nové heslá sa nezhodujú.'];
        elseif ($new === 'asd123') $pwMsg = ['danger', 'Zvoľte iné heslo ako predvolené.'];
        elseif (md_admin_set_password($new)) {
            // nový hash => nové prihlásenie (ostatné prihlásené zariadenia sa odhlásia)
            session_regenerate_id(true);
            $_SESSION['md_admin'] = hash('sha256', 'md-admin|' . md_admin_hash_reload());
            $pwMsg = ['success', 'Heslo bolo zmenené. Na serveri je uložený iba jeho hash.'];
        } else $pwMsg = ['danger', 'Heslo sa nepodarilo uložiť – priečinok storage/private nie je zapisovateľný.'];
    } elseif ($action === 'maintenance' && is_admin()) {
        $r = md_maintenance(true);
        $mtMsg = 'Údržba hotová: zmazaných ' . $r['images'] . ' starých obrázkov' . ($r['bytes'] ? ' (' . human_bytes($r['bytes']) . ')' : '') . ' a ' . $r['cache'] . ' starých súborov cache.';
    }
}

$isAdmin = is_admin();
$next = $_GET['dalej'] ?? '';

// prehľad pre prihláseného správcu
$stats = ['images' => 0, 'bytes' => 0, 'hidden' => 0, 'clients' => 0, 'logs' => []];
if ($isAdmin) {
    $clients = [];
    foreach (glob(STORAGE_DIR . '*/*.json') ?: [] as $jf) {
        $meta = json_decode((string)@file_get_contents($jf), true);
        $png = substr($jf, 0, -5) . '.png';
        if (!is_array($meta) || !is_file($png)) continue;
        $stats['images']++;
        $stats['bytes'] += filesize($png);
        if (!empty($meta['hidden'])) $stats['hidden']++;
        $clients[$meta['client'] ?? ''] = true;
    }
    $stats['clients'] = count($clients);
    foreach (glob(LOG_DIR . 'generovanie-*.txt') ?: [] as $f) {
        if (preg_match('/generovanie-(\d{4}-\d{2})\.txt$/', $f, $x)) $stats['logs'][$x[1]] = filesize($f);
    }
    krsort($stats['logs']);
}

add_layout("header", array("title" => "Správca | Match Day", "noindex" => true));
?>

<section class="hero hero-sm">
	<div class="container-xxl">
		<span class="hero-eyebrow mb-3"><i class="bi bi-shield-lock"></i> <?= $isAdmin ? 'Prihlásený správca' : 'Iba pre správcu' ?></span>
		<h1 class="mb-2">Správa <span class="accent">aplikácie</span></h1>
		<p class="lead mb-0"><?= $isAdmin ? 'Galéria všetkých prehliadačov a log generovania.' : 'Prihláste sa heslom správcu.' ?></p>
	</div>
</section>

<div class="container-xxl overlap">
	<?php if (GALLERY_ADMIN_KEY === ''): ?>
		<div class="card-app"><div class="empty-state">
			<i class="bi bi-key"></i>
			Správca nie je nastavený.
			<div class="small mt-1">V súbore <code>config.local.php</code> vyplňte <code>GALLERY_ADMIN_KEY</code> (viď <code>config.local.example.php</code>).</div>
		</div></div>

	<?php elseif (!$isAdmin): ?>
		<div class="row justify-content-center">
			<div class="col-md-7 col-lg-5">
				<div class="card-app">
					<div class="card-body p-4">
						<?php if (isset($_GET['odhlaseny'])): ?>
							<div class="alert alert-success py-2 small"><i class="bi bi-check-circle me-1"></i>Boli ste odhlásený.</div>
						<?php endif; ?>
						<?php if ($error): ?>
							<div class="alert alert-danger py-2 small" role="alert"><i class="bi bi-exclamation-triangle me-1"></i><?= plain($error) ?></div>
						<?php endif; ?>
						<form method="post" action="<?= url('spravca') ?>" autocomplete="on">
							<input type="hidden" name="action" value="login">
							<input type="hidden" name="csrf" value="<?= plain(csrf_token()) ?>">
							<input type="hidden" name="next" value="<?= plain($next) ?>">
							<input type="text" name="username" value="spravca" autocomplete="username" hidden>
							<label class="form-label fw-semibold" for="admPass">Heslo správcu</label>
							<div class="input-group mb-3">
								<span class="input-group-text"><i class="bi bi-key"></i></span>
								<input type="password" class="form-control" id="admPass" name="password" required autofocus autocomplete="current-password">
								<button class="btn btn-outline-secondary" type="button" id="admShow" title="Zobraziť heslo" aria-label="Zobraziť heslo"><i class="bi bi-eye"></i></button>
							</div>
							<button class="btn btn-primary w-100" type="submit"><i class="bi bi-box-arrow-in-right me-1"></i>Prihlásiť</button>
						</form>
					</div>
				</div>
			</div>
		</div>
		<script>
		document.getElementById('admShow').addEventListener('click', function () {
			const i = document.getElementById('admPass');
			i.type = i.type === 'password' ? 'text' : 'password';
			this.querySelector('i').className = 'bi ' + (i.type === 'password' ? 'bi-eye' : 'bi-eye-slash');
		});
		</script>

	<?php else: ?>
		<?php if (md_admin_is_default()): ?>
			<div class="alert alert-warning d-flex gap-2 align-items-start"><i class="bi bi-exclamation-triangle-fill"></i>
				<div>Používate predvolené heslo <code>asd123</code>. Zmeňte ho nižšie v časti <a href="#heslo">Zmeniť heslo</a>.</div></div>
		<?php endif; ?>
		<?php if (!empty($mtMsg)): ?><div class="alert alert-success"><i class="bi bi-check-circle me-1"></i><?= plain($mtMsg) ?></div><?php endif; ?>
		<?php if (md_sportnet_down()): ?><div class="alert alert-danger"><i class="bi bi-cloud-slash me-1"></i>Sportnet momentálne neodpovedá – stránky zobrazujú uložené údaje.</div><?php endif; ?>
		<div class="row g-3 mb-4">
			<div class="col-6 col-lg-3"><div class="stat-tile"><div class="stat-num"><?= $stats['images'] ?></div><div class="stat-label">obrázkov na serveri</div></div></div>
			<div class="col-6 col-lg-3"><div class="stat-tile"><div class="stat-num"><?= human_bytes($stats['bytes']) ?></div><div class="stat-label">obsadené miesto</div></div></div>
			<div class="col-6 col-lg-3"><div class="stat-tile"><div class="stat-num"><?= $stats['clients'] ?></div><div class="stat-label">rôznych prehliadačov</div></div></div>
			<div class="col-6 col-lg-3"><div class="stat-tile"><div class="stat-num"><?= $stats['hidden'] ?></div><div class="stat-label">skrytých používateľom</div></div></div>
		</div>

		<div class="row g-4">
			<div class="col-md-6">
				<a class="card-app admin-link h-100" href="<?= url('galeria?vsetky=1') ?>">
					<i class="bi bi-images"></i>
					<div><div class="fw-semibold">Galéria všetkých prehliadačov</div><div class="small text-muted-2">Prezeranie a mazanie obrázkov uložených na serveri</div></div>
					<i class="bi bi-chevron-right ms-auto"></i>
				</a>
			</div>
			<div class="col-md-6">
				<a class="card-app admin-link h-100" href="<?= url('kontrola') ?>">
					<i class="bi bi-activity"></i>
					<div><div class="fw-semibold">Kontrola Sportnetu</div><div class="small text-muted-2">Overenie všetkých zdrojov dát a vymazanie cache</div></div>
					<i class="bi bi-chevron-right ms-auto"></i>
				</a>
			</div>
			<div class="col-md-6">
				<a class="card-app admin-link h-100" href="<?= url('log') ?>">
					<i class="bi bi-journal-text"></i>
					<div><div class="fw-semibold">Log generovania</div><div class="small text-muted-2"><?= $stats['logs'] ? count($stats['logs']) . ' mesiacov · bez osobných údajov' : 'zatiaľ prázdny' ?></div></div>
					<i class="bi bi-chevron-right ms-auto"></i>
				</a>
			</div>
		</div>

		<?php
			$used = md_storage_usage(true); $quota = STORAGE_MAX_MB * 1048576; $pct = $quota ? min(100, round($used / $quota * 100)) : 0;
			$mt = md_json_read(md_private_file('maintenance.json'));
			$cronState = md_json_read(md_private_file('cron.json'));
		?>
		<div class="row g-4 mt-1">
			<div class="col-lg-6">
				<div class="card-app h-100"><div class="card-body">
					<h2 class="h6 section-title text-uppercase text-muted-2 mb-3"><i class="bi bi-hdd me-1"></i>Úložisko a údržba</h2>
					<div class="d-flex justify-content-between small mb-1"><span><?= human_bytes($used) ?> obsadené</span><span class="text-muted-2"><?= $quota ? 'limit ' . human_bytes($quota) : 'bez limitu' ?></span></div>
					<div class="progress mb-3" role="progressbar" aria-label="Obsadené miesto" aria-valuenow="<?= $pct ?>" aria-valuemin="0" aria-valuemax="100" style="height:10px"><div class="progress-bar <?= $pct > 85 ? 'bg-danger' : ($pct > 60 ? 'bg-warning' : 'bg-success') ?>" style="width:<?= max(2, $pct) ?>%"></div></div>
					<ul class="small text-muted-2 ps-3 mb-3">
						<li>Obrázky staršie ako <strong><?= STORAGE_KEEP_DAYS ?: '∞' ?></strong> dní sa mažú automaticky (raz za deň).</li>
						<li>Posledná údržba: <?= !empty($mt['last']['at']) ? plain(date('j. n. Y H:i', strtotime($mt['last']['at']))) . ' – zmazaných ' . (int)$mt['last']['images'] . ' obrázkov' : 'zatiaľ nebola' ?>.</li>
						<li>Nočná úloha (cron): <?= !empty($cronState['at']) ? 'naposledy ' . plain(date('j. n. Y H:i', strtotime($cronState['at']))) . (!empty($cronState['problems']) ? ' – <span class="text-danger">' . (int)$cronState['problems'] . ' problémov</span>' : ' – bez chýb') : '<span class="text-warning">ešte nebežala</span> (návod v README)' ?>.</li>
					</ul>
					<form method="post" action="<?= url('spravca') ?>">
						<input type="hidden" name="action" value="maintenance"><input type="hidden" name="csrf" value="<?= plain(csrf_token()) ?>">
						<button class="btn btn-sm btn-outline-primary" type="submit"><i class="bi bi-recycle me-1"></i>Spustiť údržbu teraz</button>
					</form>
				</div></div>
			</div>
			<div class="col-lg-6">
				<div class="card-app h-100" id="heslo"><div class="card-body">
					<h2 class="h6 section-title text-uppercase text-muted-2 mb-3"><i class="bi bi-key me-1"></i>Zmeniť heslo</h2>
					<?php if (!empty($pwMsg)): ?><div class="alert alert-<?= $pwMsg[0] ?> py-2 small"><?= plain($pwMsg[1]) ?></div><?php endif; ?>
					<form method="post" action="<?= url('spravca') ?>#heslo" autocomplete="off">
						<input type="hidden" name="action" value="password"><input type="hidden" name="csrf" value="<?= plain(csrf_token()) ?>">
						<input type="text" name="username" value="spravca" autocomplete="username" hidden>
						<input type="password" class="form-control form-control-sm mb-2" name="current" placeholder="Súčasné heslo" required autocomplete="current-password">
						<input type="password" class="form-control form-control-sm mb-2" name="new" placeholder="Nové heslo (aspoň 8 znakov)" minlength="8" required autocomplete="new-password">
						<input type="password" class="form-control form-control-sm mb-3" name="new2" placeholder="Nové heslo znova" minlength="8" required autocomplete="new-password">
						<button class="btn btn-sm btn-primary" type="submit"><i class="bi bi-check2 me-1"></i>Uložiť nové heslo</button>
						<div class="form-text">Na serveri sa uloží iba hash hesla (storage/private). Zmena hesla v config.local.php ho prepíše.</div>
					</form>
				</div></div>
			</div>
		</div>

		<form method="post" action="<?= url('spravca') ?>" class="mt-4 text-center">
			<input type="hidden" name="action" value="logout">
			<input type="hidden" name="csrf" value="<?= plain(csrf_token()) ?>">
			<button class="btn btn-outline-secondary" type="submit"><i class="bi bi-box-arrow-right me-1"></i>Odhlásiť</button>
		</form>
	<?php endif; ?>
</div>

<?php add_layout("footer"); ?>
