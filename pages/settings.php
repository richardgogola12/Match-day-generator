<?php
	add_layout("header", array(
		"title"      => "Šablóny | Match Day",
		"body_class" => "page-settings",
		"scripts"    => ["js/renderer.js", "js/editor.js"],
	));
?>

<section class="hero hero-sm">
	<div class="container-xxl">
		<div class="d-flex flex-wrap justify-content-between align-items-end gap-3">
			<div>
				<span class="hero-eyebrow mb-3"><i class="bi bi-hand-index-thumb"></i> Drag &amp; drop editor</span>
				<h1 class="mb-2">Nastavenie <span class="accent">šablón</span></h1>
				<p class="lead mb-4">Potiahnite prvky myšou (alebo prstom) tam, kde ich chcete mať. Všetko sa automaticky ukladá iba vo vašom prehliadači.</p>
			</div>
		</div>
		<div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
			<ul class="nav nav-pills nav-templates" id="tplTabs" role="tablist"></ul>
			<span class="save-indicator saved text-white-50" id="saveState"><i class="bi bi-cloud-check"></i> Uložené v prehliadači</span>
		</div>
	</div>
</section>

<div class="container-xxl overlap editor-wrap">

	<div class="row g-4">
		<!-- PLÁTNO -->
		<div class="col-xl-8">
			<div class="card-app stage-card">
				<div class="stage-toolbar">
					<div class="btn-group btn-group-sm" role="group" aria-label="Späť / znova">
						<button type="button" class="btn btn-outline-secondary" id="btnUndo" title="Späť (Ctrl+Z)"><i class="bi bi-arrow-counterclockwise"></i></button>
						<button type="button" class="btn btn-outline-secondary" id="btnRedo" title="Znova (Ctrl+Y)"><i class="bi bi-arrow-clockwise"></i></button>
					</div>

					<div class="form-check form-switch mb-0 ms-1">
						<input class="form-check-input" type="checkbox" role="switch" id="optGrid">
						<label class="form-check-label small" for="optGrid">Mriežka</label>
					</div>
					<select class="form-select form-select-sm w-auto" id="optSnap" title="Prichytávanie">
						<option value="1">Bez prichytenia</option>
						<option value="5">Prichytiť 5 px</option>
						<option value="10" selected>Prichytiť 10 px</option>
						<option value="25">Prichytiť 25 px</option>
						<option value="50">Prichytiť 50 px</option>
					</select>

					<div class="ms-auto d-flex flex-wrap gap-2 align-items-center">
						<select class="form-select form-select-sm w-auto" id="optData" title="Dáta v náhľade">
							<option value="sample">Ukážkové dáta</option>
							<option value="last" disabled>Posledný zápas</option>
						</select>
						<select class="form-select form-select-sm w-auto" id="optGoals" title="Počet gólov v ukážke">
							<option value="2">2 góly</option>
							<option value="6" selected>6 gólov</option>
							<option value="12">12 gólov</option>
							<option value="20">20 gólov</option>
						</select>
						<select class="form-select form-select-sm w-auto" id="optPlayers" title="Počet hráčov v ukážke súpisky" hidden>
							<option value="16">16 hráčov</option>
							<option value="24" selected>24 hráčov</option>
							<option value="30">30 hráčov</option>
						</select>
						<button type="button" class="btn btn-sm btn-outline-secondary" id="btnTestPng" title="Stiahnuť náhľad ako PNG"><i class="bi bi-download"></i></button>
					</div>
				</div>
				<div class="stage-outer">
					<div class="stage" id="stage">
						<canvas id="stageCanvas"></canvas>
						<div class="overlay" id="overlay"></div>
						<div class="guide v" id="guideV"></div>
						<div class="guide h" id="guideH"></div>
					</div>
				</div>
				<div class="px-3 pb-3 small text-muted-2 d-none d-md-block">
					<i class="bi bi-keyboard me-1"></i> Šípky = posun o 1 px, Shift + šípky = 10 px, Delete = zmazať vlastný prvok, Esc = zrušiť výber. Žltý bod = bod ukotvenia (x, y).
				</div>
			</div>
		</div>

		<!-- PANEL -->
		<div class="col-xl-4">
			<div class="card-app">
				<div class="card-header pb-0 border-0">
					<ul class="nav nav-tabs card-header-tabs" role="tablist">
						<li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tabElements" type="button" role="tab"><i class="bi bi-layers me-1"></i>Prvky</button></li>
						<li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tabBg" type="button" role="tab"><i class="bi bi-image me-1"></i>Pozadie</button></li>
						<li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tabFonts" type="button" role="tab"><i class="bi bi-type me-1"></i>Písma</button></li>
						<li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tabBackup" type="button" role="tab"><i class="bi bi-hdd me-1"></i>Záloha</button></li>
					</ul>
				</div>
				<div class="card-body tab-content">

					<!-- Prvky -->
					<div class="tab-pane fade show active" id="tabElements" role="tabpanel">
						<div class="d-flex justify-content-between align-items-center mb-2">
							<span class="small fw-semibold text-muted-2 text-uppercase">Vrstvy</span>
							<div class="dropdown">
								<button class="btn btn-sm btn-primary dropdown-toggle rounded-pill" type="button" data-bs-toggle="dropdown" aria-expanded="false"><i class="bi bi-plus-lg me-1"></i>Pridať</button>
								<ul class="dropdown-menu dropdown-menu-end" id="addMenu">
									<li><button class="dropdown-item" data-add="text"><i class="bi bi-fonts me-2"></i>Text</button></li>
									<li><button class="dropdown-item" data-add="shape"><i class="bi bi-square-fill me-2"></i>Obdĺžnik (podklad)</button></li>
									<li><button class="dropdown-item" data-add="logo"><i class="bi bi-shield me-2"></i>Logo družstva</button></li>
									<li><button class="dropdown-item" data-add="goals"><i class="bi bi-list-ol me-2"></i>Zoznam strelcov</button></li>
									<li><button class="dropdown-item" data-add="players"><i class="bi bi-people me-2"></i>Zoznam hráčov</button></li>
									<li><button class="dropdown-item" data-add="photo"><i class="bi bi-person-bounding-box me-2"></i>Fotka hráča</button></li>
									<li><button class="dropdown-item" data-add="table"><i class="bi bi-table me-2"></i>Tabuľka súťaže</button></li>
									<li><button class="dropdown-item" data-add="fixtures"><i class="bi bi-calendar-week me-2"></i>Zoznam zápasov</button></li>
									<li><button class="dropdown-item" data-add="compare"><i class="bi bi-bar-chart-steps me-2"></i>Porovnanie tímov</button></li>
									<li><button class="dropdown-item" data-add="formbadges"><i class="bi bi-circle-half me-2"></i>Forma (V/R/P)</button></li>
								</ul>
							</div>
						</div>
						<ul class="list-group layer-list mb-3" id="layers"></ul>

						<div id="props" class="props">
							<div class="text-center text-muted-2 small py-4 border rounded-3" style="border-style:dashed !important">
								<i class="bi bi-cursor fs-4 d-block mb-1"></i>
								Kliknite na prvok v náhľade alebo v zozname vrstiev.
							</div>
						</div>
					</div>

					<!-- Pozadie -->
					<div class="tab-pane fade" id="tabBg" role="tabpanel">
						<div class="bg-thumb mb-3" id="bgThumb"></div>
						<label class="bg-drop d-block mb-2" id="bgDrop">
							<i class="bi bi-cloud-arrow-up fs-3 d-block"></i>
							<span class="fw-semibold">Nahrať vlastné pozadie</span><br>
							<span class="small">Pretiahnite sem obrázok alebo kliknite (PNG, JPG, WEBP)</span>
							<input type="file" accept="image/*" id="bgFile" hidden>
						</label>
						<div class="d-flex gap-2 mb-4">
							<button type="button" class="btn btn-sm btn-outline-secondary flex-fill" id="bgReset"><i class="bi bi-arrow-counterclockwise me-1"></i>Predvolené pozadie</button>
						</div>

						<h6 class="small fw-semibold text-muted-2 text-uppercase">Rozmer obrázka</h6>
						<select class="form-select form-select-sm mb-2" id="sizePreset">
							<option value="">Vlastný rozmer…</option>
							<option value="1000x1000">Štvorec 1000 × 1000</option>
							<option value="1080x1080">Instagram príspevok 1080 × 1080</option>
							<option value="1080x1350">Instagram portrét 1080 × 1350</option>
							<option value="1080x1920">Story / Reels 1080 × 1920</option>
							<option value="1200x630">Facebook / web 1200 × 630</option>
						</select>
						<div class="row g-2 align-items-end">
							<div class="col-5"><label class="form-label small mb-1" for="tplW">Šírka (px)</label><input type="number" class="form-control form-control-sm" id="tplW" min="200" max="4000"></div>
							<div class="col-5"><label class="form-label small mb-1" for="tplH">Výška (px)</label><input type="number" class="form-control form-control-sm" id="tplH" min="200" max="4000"></div>
							<div class="col-2"><button type="button" class="btn btn-sm btn-outline-secondary w-100" id="sizeFromBg" title="Podľa rozmeru pozadia"><i class="bi bi-aspect-ratio"></i></button></div>
						</div>
						<div class="form-text">Pozadie sa vždy roztiahne tak, aby vyplnilo celý obrázok.</div>
					</div>

					<!-- Písma -->
					<div class="tab-pane fade" id="tabFonts" role="tabpanel">
						<h6 class="small fw-semibold text-muted-2 text-uppercase">Písmo pre celú šablónu</h6>
						<div class="input-group mb-2">
							<select class="form-select font-select" id="fontAll" aria-label="Písmo pre celú šablónu"></select>
							<button type="button" class="btn btn-primary" id="fontAllApply"><i class="bi bi-check2 me-1"></i>Použiť</button>
						</div>
						<div class="form-check form-switch mb-1">
							<input class="form-check-input" type="checkbox" role="switch" id="fontAllTemplates">
							<label class="form-check-label small" for="fontAllTemplates">Použiť vo všetkých šablónach</label>
						</div>
						<div class="form-text mb-3">Zmení písmo všetkých textov a zoznamov strelcov. Kliknutím na ukážku nižšie písmo vyberiete; ak máte označený prvok, zmení sa hneď aj jemu.</div>

						<div class="font-showcase mb-4" id="fontShowcase"></div>

						<h6 class="small fw-semibold text-muted-2 text-uppercase">Vlastné písmo</h6>
						<label class="bg-drop d-block mb-2">
							<i class="bi bi-upload fs-4 d-block"></i>
							<span class="fw-semibold">Nahrať písmo</span><br>
							<span class="small">.ttf, .otf, .woff, .woff2 – uloží sa v prehliadači</span>
							<input type="file" accept=".ttf,.otf,.woff,.woff2,font/*" id="fontFile" multiple hidden>
						</label>
						<ul class="list-group mb-2" id="customFonts"></ul>
						<div class="form-text">Používajte iba písma, ktoré máte právo použiť (napr. z Google Fonts alebo s licenciou OFL).</div>
					</div>

					<!-- Záloha -->
					<div class="tab-pane fade" id="tabBackup" role="tabpanel">
						<p class="small text-muted-2">Nastavenia sú uložené iba v tomto prehliadači (localStorage + IndexedDB). Ak ich chcete preniesť na iný počítač alebo zdieľať s kolegom, exportujte ich do súboru.</p>
						<div class="d-grid gap-2 mb-4">
							<button type="button" class="btn btn-outline-primary" id="btnExport"><i class="bi bi-box-arrow-down me-1"></i>Exportovať všetky nastavenia</button>
							<label class="btn btn-outline-secondary mb-0"><i class="bi bi-box-arrow-in-up me-1"></i>Importovať zo súboru<input type="file" accept="application/json,.json" id="importFile" hidden></label>
						</div>
						<h6 class="small fw-semibold text-muted-2 text-uppercase">Obnovenie</h6>
						<div class="d-grid gap-2">
							<button type="button" class="btn btn-outline-danger" id="btnResetTpl"><i class="bi bi-arrow-counterclockwise me-1"></i>Obnoviť túto šablónu</button>
							<button type="button" class="btn btn-danger" id="btnResetAll"><i class="bi bi-trash3 me-1"></i>Obnoviť všetky šablóny</button>
						</div>
					</div>

				</div>
			</div>
		</div>
	</div>
</div>

<!-- Potvrdenie -->
<div class="modal fade" id="confirmModal" tabindex="-1" aria-hidden="true">
	<div class="modal-dialog modal-dialog-centered modal-sm">
		<div class="modal-content">
			<div class="modal-body p-4 text-center">
				<i class="bi bi-exclamation-triangle text-danger fs-1 d-block mb-2"></i>
				<p class="mb-0" id="confirmText"></p>
			</div>
			<div class="modal-footer border-0 pt-0 justify-content-center">
				<button type="button" class="btn btn-light" data-bs-dismiss="modal">Zrušiť</button>
				<button type="button" class="btn btn-danger" id="confirmOk">Áno, obnoviť</button>
			</div>
		</div>
	</div>
</div>

<?php add_layout("footer"); ?>
