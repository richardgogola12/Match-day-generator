<?php
	$types   = image_types();
	$isAdmin = is_admin();
	$showAll = $isAdmin && !empty($_GET['vsetky']);

	add_layout("header", array(
		"title"   => "Galéria | Match Day",
		"noindex" => $showAll,
		"scripts" => ["js/gallery.js"],
	));
?>

<section class="hero hero-sm">
	<div class="container-xxl">
		<span class="hero-eyebrow mb-3"><i class="bi bi-<?= $showAll ? 'shield-lock' : 'laptop' ?>"></i> <?= $showAll ? 'Správca – všetky prehliadače' : 'Iba z tohto prehliadača' ?></span>
		<h1 class="mb-2">Galéria <span class="accent">obrázkov</span></h1>
		<p class="lead mb-0">
			<?php if ($showAll): ?>
				Všetky obrázky uložené na serveri zo všetkých prehliadačov. Zmazanie je trvalé.
			<?php else: ?>
				Obrázky, ktoré ste vygenerovali v tomto prehliadači. Rovnaký zápas a typ sa pri opätovnom vygenerovaní prepíše najnovšou verziou.
			<?php endif; ?>
		</p>
	</div>
</section>

<div class="container-xxl overlap">
	<div class="card-app mb-4">
		<div class="card-body py-3">
			<?php if ($isAdmin): ?>
				<div class="d-flex flex-wrap align-items-center gap-2 mb-3 pb-3 border-bottom">
					<div class="btn-group btn-group-sm" role="group" aria-label="Rozsah galérie">
						<a class="btn btn-outline-secondary<?= $showAll ? '' : ' active' ?>" href="<?= url('galeria') ?>"><i class="bi bi-laptop me-1"></i>Môj prehliadač</a>
						<a class="btn btn-outline-secondary<?= $showAll ? ' active' : '' ?>" href="<?= url('galeria?vsetky=1') ?>"><i class="bi bi-collection me-1"></i>Všetky prehliadače</a>
					</div>
					<?php if ($showAll): ?>
						<div class="form-check form-switch ms-sm-2 mb-0">
							<input class="form-check-input" type="checkbox" role="switch" id="gShowHidden" checked>
							<label class="form-check-label small" for="gShowHidden">Zobraziť aj skryté používateľmi</label>
						</div>
					<?php endif; ?>
					<a class="btn btn-sm btn-outline-secondary ms-auto" href="<?= url('log') ?>"><i class="bi bi-journal-text me-1"></i>Log generovania</a>
				</div>
			<?php endif; ?>

			<div class="gallery-toolbar">
				<div class="d-flex flex-wrap gap-2 filter-pills" role="group" aria-label="Typ obrázka" id="gFilter">
					<button type="button" class="btn btn-outline-secondary active" data-type="" aria-pressed="true">Všetky <span class="badge text-bg-secondary ms-1" data-count="">0</span></button>
					<?php foreach ($types as $k => $label): ?>
						<button type="button" class="btn btn-outline-secondary" data-type="<?= $k ?>" aria-pressed="false"><?= $label ?> <span class="badge text-bg-secondary ms-1" data-count="<?= $k ?>">0</span></button>
					<?php endforeach; ?>
				</div>
				<div class="gallery-toolbar-right">
					<div class="input-group">
						<span class="input-group-text"><i class="bi bi-search"></i></span>
						<input type="search" class="form-control" id="gSearch" placeholder="Hľadať tím, súťaž…" aria-label="Hľadať v galérii">
					</div>
					<select class="form-select" id="gSort" aria-label="Zoradiť">
						<option value="updated-desc">Naposledy uložené</option>
						<option value="updated-asc">Najskôr uložené</option>
						<option value="match-desc">Dátum zápasu ↓</option>
						<option value="match-asc">Dátum zápasu ↑</option>
						<option value="team-asc">Domáci tím A–Z</option>
					</select>
				</div>
			</div>
			<div class="small text-muted-2 mt-2" id="gSummary" aria-live="polite">Načítavam…</div>
		</div>
	</div>

	<div class="row row-cols-2 row-cols-md-3 row-cols-xl-4 g-3 g-md-4" id="gGrid"></div>

	<div class="text-center mt-4" id="gMoreWrap" hidden>
		<button type="button" class="btn btn-outline-primary rounded-pill px-4" id="gMore"><i class="bi bi-arrow-down-circle me-1"></i>Zobraziť ďalšie</button>
	</div>

	<div class="card-app" id="gLoading"><div class="empty-state"><div class="spinner-border text-primary mb-2"></div><div>Načítavam galériu…</div></div></div>

	<div class="card-app" id="gEmpty" hidden><div class="empty-state">
		<i class="bi bi-images"></i>
		<?php if ($showAll): ?>
			Na serveri zatiaľ nie sú uložené žiadne obrázky.
		<?php else: ?>
			V tomto prehliadači ste zatiaľ nevygenerovali žiadny obrázok.
			<div class="small mt-1">Ak ste ich tvorili v inom prehliadači, preneste si nastavenia cez <a href="<?= url('nastavenia') ?>">Šablóny → Záloha</a>.</div>
		<?php endif; ?>
		<div class="mt-3"><a href="<?= url('/') ?>" class="btn btn-primary"><i class="bi bi-search me-1"></i> Nájsť zápas</a></div>
	</div></div>

	<div class="card-app mt-3 filter-empty" hidden><div class="empty-state"><i class="bi bi-funnel"></i>Nič nezodpovedá filtru.
		<div class="mt-2"><button type="button" class="btn btn-sm btn-outline-secondary" id="gReset">Zrušiť filter</button></div>
	</div></div>
</div>

<!-- Náhľad obrázka (lightbox) -->
<div class="modal fade lightbox" id="gLightbox" tabindex="-1" aria-labelledby="lbTitle" aria-hidden="true">
	<div class="modal-dialog modal-dialog-centered modal-xl modal-fullscreen-md-down">
		<div class="modal-content">
			<div class="modal-header">
				<div class="min-w-0">
					<h5 class="modal-title text-truncate" id="lbTitle"></h5>
					<div class="small text-muted-2 text-truncate" id="lbMeta"></div>
				</div>
				<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Zavrieť"></button>
			</div>
			<div class="modal-body lightbox-stage">
				<button type="button" class="lightbox-nav prev" id="lbPrev" aria-label="Predchádzajúci obrázok"><i class="bi bi-chevron-left"></i></button>
				<img id="lbImg" alt="">
				<div class="spinner-border text-light lightbox-spin" id="lbSpin" role="status"><span class="visually-hidden">Načítavam…</span></div>
				<button type="button" class="lightbox-nav next" id="lbNext" aria-label="Ďalší obrázok"><i class="bi bi-chevron-right"></i></button>
				<span class="lightbox-pos" id="lbPos"></span>
			</div>
			<div class="modal-footer justify-content-between gap-2">
				<div class="small text-muted-2" id="lbInfo"></div>
				<div class="d-flex flex-wrap gap-2">
					<a class="btn btn-primary" id="lbDownload" download><i class="bi bi-download me-1"></i>Stiahnuť</a>
					<button type="button" class="btn btn-outline-secondary" id="lbShare" hidden><i class="bi bi-share me-1"></i>Zdieľať</button>
					<button type="button" class="btn btn-outline-secondary" id="lbCopy"><i class="bi bi-link-45deg me-1"></i>Odkaz</button>
					<a class="btn btn-outline-secondary" id="lbOpen" target="_blank" rel="noopener" title="Otvoriť v novej karte"><i class="bi bi-box-arrow-up-right"></i></a>
					<button type="button" class="btn btn-outline-danger" id="lbRemove"><i class="bi bi-eye-slash"></i></button>
				</div>
			</div>
		</div>
	</div>
</div>

<!-- Potvrdenie odstránenia -->
<div class="modal fade" id="delModal" tabindex="-1" aria-hidden="true">
	<div class="modal-dialog modal-dialog-centered modal-sm">
		<div class="modal-content">
			<div class="modal-body p-4 text-center">
				<i class="bi text-danger fs-1 d-block mb-2" id="delIcon"></i>
				<div id="delText"></div>
				<div class="small text-muted-2 mt-2" id="delNote"></div>
			</div>
			<div class="modal-footer border-0 pt-0 justify-content-center">
				<button type="button" class="btn btn-light" data-bs-dismiss="modal">Zrušiť</button>
				<button type="button" class="btn btn-danger" id="delOk">Odstrániť</button>
			</div>
		</div>
	</div>
</div>

<script>
window.MD_GALLERY = <?= json_encode([
	'all'   => $showAll,
	'admin' => $isAdmin,
	'csrf'  => $isAdmin ? csrf_token() : '',
	'root'  => ROOT_URL,
	'types' => $types,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) ?>;
</script>

<?php add_layout("footer"); ?>
