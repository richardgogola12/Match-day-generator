<?php
/**
 * Verejná stránka jedného uloženého obrázka – na zdieľanie odkazu.
 * Facebook, WhatsApp, Messenger … pri vložení odkazu zobrazia náhľad (og:image).
 *
 *   zdielaj?f=2026-10/2026-10-31_matchday_fk-a-vs-fk-b_ab12cd34.png
 */
$rel = (string)($_GET['f'] ?? '');
$png = gallery_path($rel);
if (!$png) show_404();

$meta = json_decode((string)@file_get_contents(substr($png, 0, -4) . '.json'), true) ?: [];
$types = image_types();
$typeName = $types[$meta['type'] ?? ''] ?? 'Obrázok';
$d1 = (string)($meta['d1name'] ?? '');
$d2 = (string)($meta['d2name'] ?? '');
$who = ($meta['player'] ?? '') !== '' ? $meta['player'] . ' · ' . $d1 : $d1;
$title = $d2 !== '' ? $d1 . ' – ' . $d2 : ($who !== '' ? $who : APP_NAME);
if (($meta['score1'] ?? '') !== '' && in_array($meta['type'] ?? '', ['result', 'goals', 'report'], true)) {
	$title = $d1 . ' ' . $meta['score1'] . ':' . $meta['score2'] . ' ' . $d2;
}
[$dateTxt, $timeTxt] = format_match_date($meta['startDate'] ?? '');
$desc = trim(implode(' · ', array_filter([$typeName, $meta['competition'] ?? '', $meta['category'] ?? '', $dateTxt ? $dateTxt . ($timeTxt ? ' ' . $timeTxt : '') : ''])));
$imgUrl = STORAGE_URL . $rel . '?v=' . filemtime($png);
$story = ($meta['format'] ?? '') === 'story';

add_layout("header", [
	"title" => $title . ' | ' . $typeName,
	"og"    => [
		'title' => $title, 'description' => $desc, 'image' => ROOT_URL . $imgUrl,
		'width' => (int)($meta['width'] ?? 0), 'height' => (int)($meta['height'] ?? 0),
		'url'   => ROOT_URL . url('zdielaj') . '?f=' . rawurlencode($rel),
	],
]);
?>

<section class="hero hero-sm">
	<div class="container-xxl">
		<span class="hero-eyebrow mb-3"><i class="bi bi-image"></i> <?= plain($typeName) ?></span>
		<h1 class="mb-2"><?= plain($title) ?></h1>
		<?php if ($desc): ?><p class="lead mb-0"><?= plain($desc) ?></p><?php endif; ?>
	</div>
</section>

<div class="container-xxl overlap">
	<div class="row justify-content-center">
		<div class="<?= $story ? 'col-md-6 col-lg-4' : 'col-lg-8 col-xl-7' ?>">
			<div class="card-app">
				<div class="card-body">
					<img src="<?= plain($imgUrl) ?>" class="img-fluid rounded-3 d-block mx-auto share-img" alt="<?= plain($typeName . ' – ' . $title) ?>" width="<?= (int)($meta['width'] ?? 1000) ?>" height="<?= (int)($meta['height'] ?? 1000) ?>">
					<div class="d-flex flex-wrap gap-2 justify-content-center mt-3">
						<a class="btn btn-primary" href="<?= plain($imgUrl) ?>" download="<?= plain(basename($rel)) ?>"><i class="bi bi-download me-1"></i>Stiahnuť</a>
						<button type="button" class="btn btn-outline-secondary" id="shareCopy"><i class="bi bi-link-45deg me-1"></i>Kopírovať odkaz</button>
						<a class="btn btn-outline-secondary" target="_blank" rel="noopener" href="https://www.facebook.com/sharer/sharer.php?u=<?= rawurlencode(ROOT_URL . url('zdielaj') . '?f=' . rawurlencode($rel)) ?>"><i class="bi bi-facebook me-1"></i>Facebook</a>
						<a class="btn btn-outline-secondary" target="_blank" rel="noopener" href="https://wa.me/?text=<?= rawurlencode($title . ' ' . ROOT_URL . url('zdielaj') . '?f=' . rawurlencode($rel)) ?>"><i class="bi bi-whatsapp me-1"></i>WhatsApp</a>
					</div>
				</div>
			</div>
			<div class="text-center mt-4">
				<a class="btn btn-link" href="<?= url('/') ?>"><i class="bi bi-magic me-1"></i>Vytvoriť vlastný obrázok v <?= plain(APP_NAME) ?></a>
			</div>
		</div>
	</div>
</div>

<script>
document.getElementById('shareCopy').addEventListener('click', function () {
	navigator.clipboard.writeText(location.href).then(() => window.MD && MD.toast ? MD.toast('Odkaz skopírovaný') : null);
});
</script>

<?php add_layout("footer"); ?>
