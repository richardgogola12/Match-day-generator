<?php
	add_layout("header", array(
		"title"   => "Značka klubu | Match Day",
		"scripts" => ["js/qr.js", "js/renderer.js", "js/brand.js"],
	));
?>

<section class="hero hero-sm">
	<div class="container-xxl">
		<span class="hero-eyebrow mb-3"><i class="bi bi-palette"></i> Raz nastaviť – použije sa všade</span>
		<h1 class="mb-2">Značka <span class="accent">klubu</span></h1>
		<p class="lead mb-0">Logo klubu, pás sponzorov a klubové farby sa automaticky pridajú do každého vygenerovaného obrázka. Ukladá sa iba v tomto prehliadači.</p>
	</div>
</section>

<div class="container-xxl overlap">
	<div class="row g-4">
		<div class="col-lg-6 col-xl-7 order-lg-2">
			<div class="card-app brand-preview-card">
				<div class="card-body">
					<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
						<h2 class="h6 section-title text-uppercase text-muted-2 mb-0">Náhľad</h2>
						<div class="d-flex flex-wrap gap-2">
							<select class="form-select form-select-sm w-auto" id="bpType" aria-label="Šablóna v náhľade">
								<option value="matchday">Match day</option>
								<option value="result">Výsledok</option>
								<option value="table">Tabuľka</option>
								<option value="weekend">Víkend klubu</option>
							</select>
							<div class="btn-group btn-group-sm" role="group" aria-label="Formát" id="bpFormat">
								<button type="button" class="btn btn-outline-secondary active" data-format="square">1:1</button>
								<button type="button" class="btn btn-outline-secondary" data-format="story">Story</button>
							</div>
						</div>
					</div>
					<div class="preview-frame" id="bpFrame"><canvas id="bpOut" width="1000" height="1000" aria-label="Náhľad značky"></canvas></div>
				</div>
			</div>
		</div>

		<div class="col-lg-6 col-xl-5 order-lg-1">
			<!-- Farby -->
			<div class="card-app mb-4">
				<div class="card-body">
					<div class="d-flex justify-content-between align-items-center mb-3">
						<h2 class="h6 section-title text-uppercase text-muted-2 mb-0">Farby klubu</h2>
						<div class="form-check form-switch mb-0">
							<input class="form-check-input" type="checkbox" role="switch" id="bcOn">
							<label class="form-check-label small" for="bcOn">Použiť</label>
						</div>
					</div>
					<div class="brand-presets mb-3" id="bcPresets"></div>
					<div class="row g-2 mb-2">
						<div class="col-4">
							<label class="form-label small fw-semibold" for="bcPrimary">Hlavná</label>
							<input type="color" class="form-control form-control-color w-100" id="bcPrimary" value="#e63946">
						</div>
						<div class="col-4">
							<label class="form-label small fw-semibold" for="bcAccent">Doplnková</label>
							<input type="color" class="form-control form-control-color w-100" id="bcAccent" value="#ffd166">
						</div>
						<div class="col-4">
							<label class="form-label small fw-semibold" for="bcBase">Tmavá (pozadie)</label>
							<input type="color" class="form-control form-control-color w-100" id="bcBase" value="#0b2a1a">
						</div>
					</div>
					<button type="button" class="btn btn-sm btn-outline-primary" id="bcFromLogo" disabled><i class="bi bi-eyedropper me-1"></i>Zistiť farby z loga</button>
					<div class="form-text mt-2">Prefarbia sa predvolené pozadia aj červené / žlté prvky šablón. Vlastné nahraté pozadia ostanú bez zmeny.</div>
				</div>
			</div>

			<!-- Logo -->
			<div class="card-app mb-4">
				<div class="card-body">
					<div class="d-flex justify-content-between align-items-center mb-3">
						<h2 class="h6 section-title text-uppercase text-muted-2 mb-0">Logo klubu</h2>
						<div class="form-check form-switch mb-0">
							<input class="form-check-input" type="checkbox" role="switch" id="blOn">
							<label class="form-check-label small" for="blOn">Vložiť do obrázkov</label>
						</div>
					</div>
					<div class="d-flex align-items-center gap-3 mb-3">
						<div class="brand-thumb" id="blThumb"><i class="bi bi-shield"></i><img alt="" hidden></div>
						<div class="d-grid gap-2 flex-fill">
							<label class="btn btn-sm btn-outline-primary mb-0"><i class="bi bi-upload me-1"></i>Nahrať logo (PNG)<input type="file" accept="image/*" id="blFile" hidden></label>
							<button type="button" class="btn btn-sm btn-outline-danger" id="blRemove" hidden><i class="bi bi-trash3 me-1"></i>Odstrániť</button>
						</div>
					</div>
					<div class="row g-2">
						<div class="col-6">
							<label class="form-label small fw-semibold" for="blPos">Umiestnenie</label>
							<select class="form-select form-select-sm" id="blPos">
								<option value="tl">Vľavo hore</option>
								<option value="tr">Vpravo hore</option>
								<option value="bl">Vľavo dole</option>
								<option value="br">Vpravo dole</option>
							</select>
						</div>
						<div class="col-6">
							<label class="form-label small fw-semibold" for="blSize">Veľkosť <span class="text-muted-2" id="blSizeTxt"></span></label>
							<input type="range" class="form-range" id="blSize" min="60" max="240" step="5">
						</div>
						<div class="col-12">
							<label class="form-label small fw-semibold" for="blOpacity">Priehľadnosť <span class="text-muted-2" id="blOpacityTxt"></span></label>
							<input type="range" class="form-range" id="blOpacity" min="0.3" max="1" step="0.05">
						</div>
					</div>
				</div>
			</div>

			<!-- Sponzori -->
			<div class="card-app mb-4">
				<div class="card-body">
					<div class="d-flex justify-content-between align-items-center mb-3">
						<h2 class="h6 section-title text-uppercase text-muted-2 mb-0">Pás sponzorov</h2>
						<div class="form-check form-switch mb-0">
							<input class="form-check-input" type="checkbox" role="switch" id="bsOn">
							<label class="form-check-label small" for="bsOn">Vložiť do obrázkov</label>
						</div>
					</div>
					<div class="brand-strip mb-3" id="bsThumb"><span class="small text-muted-2">Jeden obrázok so všetkými logami sponzorov vedľa seba (napr. 2000 × 200 px).</span><img alt="" hidden></div>
					<div class="d-flex gap-2 mb-3">
						<label class="btn btn-sm btn-outline-primary mb-0 flex-fill"><i class="bi bi-upload me-1"></i>Nahrať pás sponzorov<input type="file" accept="image/*" id="bsFile" hidden></label>
						<button type="button" class="btn btn-sm btn-outline-danger" id="bsRemove" hidden><i class="bi bi-trash3"></i></button>
					</div>
					<div class="row g-2 align-items-end">
						<div class="col-7">
							<label class="form-label small fw-semibold" for="bsHeight">Výška <span class="text-muted-2" id="bsHeightTxt"></span></label>
							<input type="range" class="form-range" id="bsHeight" min="50" max="180" step="2">
						</div>
						<div class="col-5">
							<div class="form-check mb-2">
								<input class="form-check-input" type="checkbox" id="bsStrip">
								<label class="form-check-label small" for="bsStrip">Biely podklad</label>
							</div>
						</div>
					</div>
					<div class="form-text">Pás sa vloží na spodok obrázka – v šablónach si potom môžete posunúť prvky vyššie. V story formáte je pod obrázkom.</div>
				</div>
			</div>

			<div class="text-center">
				<button type="button" class="btn btn-link text-danger" id="bReset"><i class="bi bi-arrow-counterclockwise me-1"></i>Vrátiť všetko na predvolené</button>
			</div>
		</div>
	</div>
</div>

<?php add_layout("footer"); ?>
