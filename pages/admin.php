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
		<?php if (GALLERY_ADMIN_KEY === 'asd123'): ?>
			<div class="alert alert-warning d-flex gap-2 align-items-start"><i class="bi bi-exclamation-triangle-fill"></i>
				<div>Používate predvolené heslo <code>asd123</code>. Na verejnom serveri ho zmeňte v súbore <code>config.local.php</code> (<code>define('GALLERY_ADMIN_KEY', 'nové-heslo');</code>).</div></div>
		<?php endif; ?>
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

		<form method="post" action="<?= url('spravca') ?>" class="mt-4 text-center">
			<input type="hidden" name="action" value="logout">
			<input type="hidden" name="csrf" value="<?= plain(csrf_token()) ?>">
			<button class="btn btn-outline-secondary" type="submit"><i class="bi bi-box-arrow-right me-1"></i>Odhlásiť</button>
		</form>
	<?php endif; ?>
</div>

<?php add_layout("footer"); ?>
