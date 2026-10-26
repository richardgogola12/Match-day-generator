<?php add_layout("header", array("title" => "Stránka nenájdená")); ?>
<section class="hero hero-sm">
	<div class="container-xxl">
		<h1 class="mb-2">404 <span class="accent">offside</span></h1>
		<p class="lead mb-0">Táto stránka neexistuje.</p>
	</div>
</section>
<div class="container-xxl overlap">
	<div class="card-app"><div class="empty-state">
		<i class="bi bi-signpost-split"></i>
		<a href="<?= url('/') ?>" class="btn btn-primary mt-2"><i class="bi bi-house me-1"></i> Domov</a>
	</div></div>
</div>
<?php add_layout("footer"); ?>
