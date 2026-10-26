<?php
	$active = current_page();
	$isAdminNav = is_admin();
	if ($isAdminNav || !empty($noindex)) no_store_headers(); // stránky s menu správcu nesmú ísť do cache
	$nav = function ($pages) use ($active) {
		return in_array($active, (array)$pages, true) ? ' active" aria-current="page' : '';
	};
	// skripty stránky sa vložia do pätičky
	$GLOBALS['page_scripts'] = $_scripts ?? [];
?>
<!DOCTYPE html>
<html lang="sk" data-bs-theme="light">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title><?= isset($title) ? $title : APP_NAME ?></title>
	<meta name="description" content="Generátor zápasových obrázkov – Match day, výsledok a strelci.">
	<meta name="keywords" content="match-day, generátor, futbal, šport">
	<meta name="author" content="RGA">
	<meta name="theme-color" content="#0b2a1a">
	<?php if (!empty($noindex)): ?><meta name="robots" content="noindex, nofollow, noarchive"><meta name="referrer" content="no-referrer"><?php endif; ?>

	<!-- Favicon + PWA -->
	<link rel="icon" href="<?= url("favicon.ico") ?>" sizes="32x32">
	<link rel="icon" href="<?= asset("icons/icon.svg") ?>" type="image/svg+xml">
	<link rel="apple-touch-icon" href="<?= asset("icons/apple-touch-icon.png") ?>">
	<link rel="manifest" href="<?= url("manifest.webmanifest") ?>">
	<meta name="mobile-web-app-capable" content="yes">
	<meta name="apple-mobile-web-app-capable" content="yes">
	<meta name="apple-mobile-web-app-title" content="Match Day">
	<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">

	<!-- Téma (svetlá/tmavá) – nastaví sa ešte pred vykreslením, aby stránka neblikla -->
	<script>
		window.MD_BASE = <?= json_encode(BASE_PATH) ?>;
		(function () {
			try {
				var p = JSON.parse(localStorage.getItem('matchday.prefs.v1') || '{}');
				var t = p.theme || (matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
				document.documentElement.setAttribute('data-bs-theme', t);
			} catch (e) {}
		})();
	</script>

	<!-- Fonts -->
	<link rel="preconnect" href="https://fonts.googleapis.com">
	<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
	<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

	<!-- Bootstrap 5 + ikony -->
	<link rel="stylesheet" href="<?= url("includes/vendor/bootstrap/bootstrap.min.css") ?>">
	<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

	<!-- jQuery + select2 -->
	<script src="<?= url("includes/vendor/jquery/jquery.min.js") ?>"></script>
	<link rel="stylesheet" href="<?= url("includes/vendor/select2/select2.min.css") ?>">
	<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css">
	<script src="<?= url("includes/vendor/select2/select2.min.js") ?>"></script>

	<!-- App -->
	<link rel="stylesheet" href="<?= asset("css/style.css") ?>">
	<script src="<?= asset("js/storage.js") ?>"></script>
</head>
<body class="<?= isset($body_class) ? $body_class : '' ?>">

<nav class="navbar navbar-expand-md app-navbar sticky-top">
	<div class="container-xxl">
		<a class="navbar-brand d-flex align-items-center gap-2" href="<?= url("/") ?>">
			<img class="brand-mark" src="<?= asset("icons/icon.svg") ?>" alt="" width="36" height="36">
			<span class="brand-text">Match<span>Day</span></span>
		</a>

		<div class="d-flex align-items-center gap-2 order-md-last">
			<button class="btn btn-primary btn-sm rounded-pill px-3 install-btn" type="button" id="installBtn" hidden title="Nainštalovať aplikáciu do zariadenia">
				<i class="bi bi-download me-1"></i><span class="d-none d-sm-inline">Nainštalovať</span><span class="d-sm-none">Inštalovať</span>
			</button>
			<button class="btn btn-icon" type="button" id="themeToggle" title="Prepnúť svetlý / tmavý režim" aria-label="Prepnúť tému">
				<i class="bi bi-moon-stars theme-icon-light"></i>
				<i class="bi bi-sun theme-icon-dark"></i>
			</button>
			<button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav" aria-controls="mainNav" aria-expanded="false" aria-label="Menu">
				<i class="bi bi-list fs-3"></i>
			</button>
		</div>

		<div class="collapse navbar-collapse" id="mainNav">
			<ul class="navbar-nav ms-auto me-md-3 gap-md-1">
				<li class="nav-item">
					<a class="nav-link<?= $nav(['', 'match-day-season-detail', 'generator']) ?>" href="<?= url("/") ?>">
						<i class="bi bi-search"></i><span>Zápasy</span>
					</a>
				</li>
				<li class="nav-item">
					<a class="nav-link<?= $nav('galeria') ?>" href="<?= url("galeria") ?>">
						<i class="bi bi-images"></i><span>Galéria</span>
					</a>
				</li>
				<li class="nav-item">
					<a class="nav-link<?= $nav('nastavenia') ?>" href="<?= url("nastavenia") ?>">
						<i class="bi bi-sliders"></i><span>Nastavenie šablón</span>
					</a>
				</li>
				<li class="nav-item">
					<a class="nav-link<?= $nav('navod') ?>" href="<?= url("navod") ?>">
						<i class="bi bi-book"></i><span>Návod</span>
					</a>
				</li>
				<?php if ($isAdminNav): ?>
				<li class="nav-item dropdown">
					<a class="nav-link dropdown-toggle<?= $nav(['spravca', 'log']) ?>" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
						<i class="bi bi-shield-lock"></i><span>Správca</span>
					</a>
					<ul class="dropdown-menu dropdown-menu-end app-dropdown">
						<li><a class="dropdown-item" href="<?= url("spravca") ?>"><i class="bi bi-speedometer2"></i>Prehľad</a></li>
						<li><a class="dropdown-item" href="<?= url("galeria?vsetky=1") ?>"><i class="bi bi-collection"></i>Galéria všetkých</a></li>
						<li><a class="dropdown-item" href="<?= url("log") ?>"><i class="bi bi-journal-text"></i>Log generovania</a></li>
						<li><hr class="dropdown-divider"></li>
						<li>
							<form method="post" action="<?= url("spravca") ?>" class="m-0">
								<input type="hidden" name="action" value="logout">
								<input type="hidden" name="csrf" value="<?= plain(csrf_token()) ?>">
								<button type="submit" class="dropdown-item text-danger"><i class="bi bi-box-arrow-right"></i>Odhlásiť</button>
							</form>
						</li>
					</ul>
				</li>
				<?php endif; ?>
			</ul>
		</div>
	</div>
</nav>

<main class="app-main">
