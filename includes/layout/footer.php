</main>

<footer class="app-footer">
	<div class="container-xxl d-flex flex-column flex-sm-row justify-content-between align-items-center gap-2">
		<span><i class="bi bi-dribbble me-1"></i> Match Day Generator</span>
		<span class="text-body-secondary small d-flex flex-wrap justify-content-center gap-2 gap-sm-3">
			<span><i class="bi bi-shield-lock me-1"></i>Nastavenia a galéria sú viazané na tento prehliadač</span>
			<?php if (GALLERY_ADMIN_KEY !== '' && !is_admin()): ?><a class="footer-link" href="<?= url('spravca') ?>" rel="nofollow">Správca</a><?php endif; ?>
		</span>
	</div>
</footer>

<!-- Toasty -->
<div class="toast-container position-fixed top-0 end-0 p-3" id="toastArea" style="margin-top:64px"></div>

<!-- Späť hore -->
<button type="button" class="back-to-top" id="backToTop" aria-label="Späť na začiatok stránky" title="Hore">
	<i class="bi bi-arrow-up"></i>
</button>

<!-- Návod na inštaláciu (iPhone / iPad – Safari nemá tlačidlo inštalácie) -->
<div class="modal fade" id="iosInstallModal" tabindex="-1" aria-hidden="true">
	<div class="modal-dialog modal-dialog-centered">
		<div class="modal-content">
			<div class="modal-header border-0">
				<h5 class="modal-title"><i class="bi bi-phone me-2 text-primary"></i>Pridať na plochu</h5>
				<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Zavrieť"></button>
			</div>
			<div class="modal-body pt-0">
				<ol class="mb-0 d-grid gap-2">
					<li>V Safari ťuknite na tlačidlo <strong>Zdieľať</strong> <i class="bi bi-box-arrow-up"></i> v spodnej lište.</li>
					<li>Zvoľte <strong>Pridať na plochu</strong> <i class="bi bi-plus-square"></i>.</li>
					<li>Potvrďte tlačidlom <strong>Pridať</strong>. Aplikácia sa objaví na ploche ako ikona.</li>
				</ol>
			</div>
		</div>
	</div>
</div>

<script src="<?= url("includes/vendor/bootstrap/bootstrap.bundle.min.js") ?>"></script>
<script src="<?= asset("js/app.js") ?>"></script>
<?php foreach ($GLOBALS['page_scripts'] ?? [] as $s): ?>
<script src="<?= asset($s) ?>"></script>
<?php endforeach; ?>
</body>
</html>
