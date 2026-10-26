<?php add_layout("header", array("title" => "Ste offline | Match Day")); ?>
<section class="hero hero-sm">
	<div class="container-xxl">
		<h1 class="mb-2">Ste <span class="accent">offline</span></h1>
		<p class="lead mb-0">Na načítanie zápasov je potrebné pripojenie na internet.</p>
	</div>
</section>
<div class="container-xxl overlap">
	<div class="card-app"><div class="empty-state">
		<i class="bi bi-wifi-off"></i>
		<p>Skontrolujte pripojenie a skúste to znova. Uložené šablóny a nastavenia ostávajú vo vašom zariadení.</p>
		<button type="button" class="btn btn-primary" onclick="location.reload()"><i class="bi bi-arrow-clockwise me-1"></i> Skúsiť znova</button>
		<a href="<?= url('nastavenia') ?>" class="btn btn-outline-secondary ms-1"><i class="bi bi-sliders me-1"></i> Šablóny</a>
	</div></div>
</div>
<?php add_layout("footer"); ?>
