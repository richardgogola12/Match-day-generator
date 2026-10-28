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
	<?php if (!empty($_og)): ?>
	<meta property="og:type" content="website">
	<meta property="og:site_name" content="<?= plain(APP_NAME) ?>">
	<meta property="og:title" content="<?= plain($_og['title'] ?? '') ?>">
	<?php if (!empty($_og['description'])): ?><meta property="og:description" content="<?= plain($_og['description']) ?>"><?php endif; ?>
	<?php if (!empty($_og['url'])): ?><meta property="og:url" content="<?= plain($_og['url']) ?>"><?php endif; ?>
	<?php if (!empty($_og['image'])): ?>
	<meta property="og:image" content="<?= plain($_og['image']) ?>">
	<?php if (!empty($_og['width'])): ?><meta property="og:image:width" content="<?= (int)$_og['width'] ?>"><meta property="og:image:height" content="<?= (int)$_og['height'] ?>"><?php endif; ?>
	<meta name="twitter:card" content="summary_large_image">
	<meta name="twitter:image" content="<?= plain($_og['image']) ?>">
	<?php endif; ?>
	<?php endif; ?>
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
		// obrázky (logá, fotky), ktoré sa nenačítali: 1) nový pokus, 2) originál / proxy servera, 3) náhradná ikona
		window.addEventListener('error', function (e) {
			var img = e.target;
			if (!img || img.tagName !== 'IMG') return;
			var src = img.getAttribute('src') || '';
			if (!src || /^(data|blob):/.test(src)) return;
			var step = +(img.getAttribute('data-md-try') || 0);
			if (step > 2) return;
			img.setAttribute('data-md-try', step + 1);
			var m = src.match(/api-logo\?(?:[^#]*&)?url=([^&#]+)/);
			var orig = img.getAttribute('data-md-orig') || (m ? decodeURIComponent(m[1]) : src);
			img.setAttribute('data-md-orig', orig);
			var proxied = window.MD_BASE + '/api-logo?fb=1&url=' + encodeURIComponent(orig);
			var photo = img.closest && img.closest('.player-photo');
			var ph = 'data:image/svg+xml,' + encodeURIComponent('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M12 2 4 5v6c0 5 3.4 9.4 8 11 4.6-1.6 8-6 8-11V5z" fill="#94a3b8" fill-opacity=".35" stroke="#94a3b8" stroke-width="1.2"/></svg>');
			if (m && step === 0) setTimeout(function () { img.src = src + '&r=' + Date.now(); }, 500 + Math.random() * 900);
			else if (m && step === 1) img.src = orig;
			else if (!m && step === 0) img.src = proxied;
			else if (!photo) { img.setAttribute('data-md-try', 9); img.src = ph; img.classList.add('img-failed'); }
		}, true);
		(function () {
			try {
				var p = JSON.parse(localStorage.getItem('matchday.prefs.v1') || '{}');
				var t = p.theme || (matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
				document.documentElement.setAttribute('data-bs-theme', t);
			} catch (e) {}
		})();
	</script>

	<!-- Písma a ikony sú na našom serveri (rýchlejšie, bez Google Fonts / CDN – GDPR) -->
	<link rel="preload" href="<?= url("assets/fonts/inter-latin.woff") ?>" as="font" type="font/woff" crossorigin>
	<link rel="preload" href="<?= url("assets/vendor/icons/fonts/bootstrap-icons.woff2?dd67030699838ea613ee6dbda90effa6") ?>" as="font" type="font/woff2" crossorigin>
	<link rel="stylesheet" href="<?= url("includes/vendor/bootstrap/bootstrap.min.css") ?>">
	<link rel="stylesheet" href="<?= asset("vendor/icons/bootstrap-icons.min.css") ?>">
	<script src="<?= url("includes/vendor/jquery/jquery.min.js") ?>"></script>

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

		<?php if ($active !== ''): // na úvodnej stránke je veľké hľadanie priamo na stránke ?>
		<form class="gsearch gsearch-nav" role="search" autocomplete="off">
			<i class="bi bi-search gsearch-icon" aria-hidden="true"></i>
			<input type="search" class="form-control gsearch-input" placeholder="Hľadať klub alebo hráča…" aria-label="Hľadať klub, družstvo alebo hráča" aria-expanded="false" aria-controls="gsMenuNav" data-gsearch data-gsearch-main>
			<kbd class="gsearch-kbd" aria-hidden="true">/</kbd>
			<div class="gsearch-menu" id="gsMenuNav" role="listbox" hidden></div>
		</form>
		<?php endif; ?>

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
					<a class="nav-link<?= $nav(['', 'match-day-season-detail', 'generator', 'druzstvo', 'hrac', 'zapas']) ?>" href="<?= url("/") ?>">
						<i class="bi bi-house"></i><span>Zápasy</span>
					</a>
				</li>
				<li class="nav-item">
					<a class="nav-link<?= $nav('galeria') ?>" href="<?= url("galeria") ?>">
						<i class="bi bi-images"></i><span>Galéria</span>
					</a>
				</li>
				<li class="nav-item">
					<a class="nav-link<?= $nav('nastavenia') ?>" href="<?= url("nastavenia") ?>">
						<i class="bi bi-sliders"></i><span>Šablóny</span>
					</a>
				</li>
				<li class="nav-item">
					<a class="nav-link<?= $nav('znacka') ?>" href="<?= url("znacka") ?>">
						<i class="bi bi-palette"></i><span>Značka</span>
					</a>
				</li>
				<li class="nav-item">
					<a class="nav-link<?= $nav('navod') ?>" href="<?= url("navod") ?>">
						<i class="bi bi-book"></i><span>Návod</span>
					</a>
				</li>
				<?php if ($isAdminNav): ?>
				<li class="nav-item dropdown">
					<a class="nav-link dropdown-toggle<?= $nav(['spravca', 'log', 'kontrola', 'nastavenia-spravcu']) ?>" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
						<i class="bi bi-shield-lock"></i><span>Správca</span>
					</a>
					<ul class="dropdown-menu dropdown-menu-end app-dropdown">
						<li><a class="dropdown-item" href="<?= url("spravca") ?>"><i class="bi bi-speedometer2"></i>Prehľad</a></li>
						<li><a class="dropdown-item" href="<?= url("galeria?vsetky=1") ?>"><i class="bi bi-collection"></i>Galéria všetkých</a></li>
						<li><a class="dropdown-item" href="<?= url("log") ?>"><i class="bi bi-journal-text"></i>Log generovania</a></li>
						<li><a class="dropdown-item" href="<?= url("kontrola") ?>"><i class="bi bi-activity"></i>Kontrola Sportnetu</a></li>
						<li><a class="dropdown-item" href="<?= url("nastavenia-spravcu") ?>"><i class="bi bi-gear"></i>Nastavenia</a></li>
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

<?php if (md_sportnet_down()): ?>
<div class="sportnet-down" role="status"><div class="container-xxl"><i class="bi bi-cloud-slash me-2"></i>Sportnet momentálne neodpovedá – zobrazujem naposledy uložené údaje. Skúste to o chvíľu znova.</div></div>
<?php endif; ?>
<main class="app-main">
