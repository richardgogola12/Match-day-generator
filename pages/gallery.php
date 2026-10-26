<?php
	$types = image_types();
	$isAdmin = gallery_is_admin($_GET['admin'] ?? null);

	add_layout("header", array("title" => "Galéria | Match Day"));
?>

<section class="hero hero-sm">
	<div class="container-xxl">
		<span class="hero-eyebrow mb-3"><i class="bi bi-<?= $isAdmin ? 'shield-lock' : 'laptop' ?>"></i> <?= $isAdmin ? 'Administrátor – všetky prehliadače' : 'Iba z tohto prehliadača' ?></span>
		<h1 class="mb-2">Galéria <span class="accent">obrázkov</span></h1>
		<p class="lead mb-0">
			<?php if ($isAdmin): ?>
				Všetky obrázky uložené na serveri zo všetkých prehliadačov.
			<?php else: ?>
				Obrázky, ktoré ste vygenerovali v tomto prehliadači. Rovnaký zápas a typ sa pri opätovnom vygenerovaní prepíše najnovšou verziou.
			<?php endif; ?>
		</p>
	</div>
</section>

<div class="container-xxl overlap">
	<div class="card-app mb-4">
		<div class="card-body py-3">
			<div class="gallery-toolbar">
				<div class="btn-group filter-pills flex-wrap" role="group" aria-label="Typ obrázka" id="gFilter">
					<button type="button" class="btn btn-outline-secondary active" data-type="">Všetky <span class="badge text-bg-secondary ms-1" data-count="">0</span></button>
					<?php foreach ($types as $k => $label): ?>
						<button type="button" class="btn btn-outline-secondary" data-type="<?= $k ?>"><?= $label ?> <span class="badge text-bg-secondary ms-1" data-count="<?= $k ?>">0</span></button>
					<?php endforeach; ?>
				</div>
				<div class="d-flex gap-2 align-items-center flex-grow-1 flex-md-grow-0" style="min-width:260px">
					<div class="input-group">
						<span class="input-group-text"><i class="bi bi-search"></i></span>
						<input type="search" class="form-control" id="gSearch" placeholder="Hľadať tím, súťaž…">
					</div>
				</div>
			</div>
			<div class="small text-muted-2 mt-2" id="gSummary">Načítavam…</div>
			<?php if ($isAdmin): ?>
				<div class="d-flex flex-wrap align-items-center gap-2 mt-3 pt-3 border-top" id="logBox">
					<span class="small fw-semibold"><i class="bi bi-journal-text me-1"></i>Log generovania:</span>
					<span class="small text-muted-2" id="logList">načítavam…</span>
				</div>
			<?php endif; ?>
		</div>
	</div>

	<div class="row row-cols-2 row-cols-md-3 row-cols-xl-4 g-3 g-md-4" id="gGrid"></div>

	<div class="card-app" id="gLoading"><div class="empty-state"><div class="spinner-border text-primary mb-2"></div><div>Načítavam galériu…</div></div></div>

	<div class="card-app" id="gEmpty" hidden><div class="empty-state">
		<i class="bi bi-images"></i>
		V tomto prehliadači ste zatiaľ nevygenerovali žiadny obrázok.
		<div class="small mt-1">Ak ste ich tvorili v inom prehliadači, preneste si nastavenia cez <a href="<?= url('nastavenia') ?>">Nastavenie šablón → Záloha</a>.</div>
		<div class="mt-3"><a href="<?= url('/') ?>" class="btn btn-primary"><i class="bi bi-search me-1"></i> Nájsť zápas</a></div>
	</div></div>

	<div class="card-app mt-3 filter-empty" hidden><div class="empty-state"><i class="bi bi-funnel"></i>Nič nezodpovedá filtru.</div></div>
</div>

<!-- Potvrdenie odstránenia z galérie -->
<div class="modal fade" id="delModal" tabindex="-1" aria-hidden="true">
	<div class="modal-dialog modal-dialog-centered modal-sm">
		<div class="modal-content">
			<div class="modal-body p-4 text-center">
				<i class="bi bi-eye-slash text-danger fs-1 d-block mb-2"></i>
				Odstrániť obrázok z vašej galérie?
				<div class="small text-muted-2 mt-2">Súbor zostane uložený na serveri.</div>
			</div>
			<div class="modal-footer border-0 pt-0 justify-content-center">
				<button type="button" class="btn btn-light" data-bs-dismiss="modal">Zrušiť</button>
				<button type="button" class="btn btn-danger" id="delOk">Odstrániť</button>
			</div>
		</div>
	</div>
</div>

<script>
$(function () {
	const MD = window.MD;
	const ADMIN = <?= json_encode($isAdmin ? ($_GET['admin'] ?? '') : '') ?>;
	const ROOT = <?= json_encode(ROOT_URL) ?>;
	const TYPES = <?= json_encode($types, JSON_UNESCAPED_UNICODE) ?>;
	const MONTHS = ['Jan', 'Feb', 'Mar', 'Apr', 'Máj', 'Jún', 'Júl', 'Aug', 'Sep', 'Okt', 'Nov', 'Dec'];
	let items = [], type = '', q = '';

	const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
	const bytes = (b) => b >= 1048576 ? (b / 1048576).toFixed(1) + ' MB' : Math.max(1, Math.round(b / 1024)) + ' kB';
	function fmtDate(iso, withTime) {
		const d = new Date(iso);
		if (!iso || isNaN(d)) return '';
		const p = {};
		new Intl.DateTimeFormat('en-GB', { timeZone: 'Europe/Bratislava', year: 'numeric', month: 'numeric', day: 'numeric', hour: '2-digit', minute: '2-digit', hour12: false })
			.formatToParts(d).forEach((x) => { p[x.type] = x.value; });
		return withTime ? (+p.day) + '.' + (+p.month) + '.' + p.year + ' ' + p.hour + ':' + p.minute : (+p.day) + '. ' + MONTHS[p.month - 1] + ' ' + p.year;
	}

	function card(it) {
		const score = it.score1 !== '' && it.score1 != null ? '<strong>' + esc(it.score1) + ':' + esc(it.score2) + '</strong> · ' : '';
		const search = [it.d1name, it.d2name, it.competition, fmtDate(it.startDate)].join(' ').toLowerCase();
		const dl = (it.type || 'obrazok') + '_' + it.file.split('/').pop();
		return '<div class="col g-item" data-type="' + esc(it.type) + '" data-search="' + esc(search) + '" data-file="' + esc(it.file) + '">' +
			'<div class="card-app gallery-card">' +
			'<a class="gallery-thumb" href="' + esc(it.url) + '" target="_blank" rel="noopener" style="aspect-ratio:' + (+it.width || 1) + '/' + (+it.height || 1) + '">' +
			'<img src="' + esc(it.url) + '" alt="' + esc((it.typeName || '') + ': ' + it.d1name + ' vs ' + it.d2name) + '" loading="lazy">' +
			'<span class="badge rounded-pill text-bg-dark">' + esc(it.typeName || TYPES[it.type] || it.type) + '</span></a>' +
			'<div class="card-body">' +
			'<div class="g-title">' + esc(it.d1name) + ' <span class="text-muted-2">vs</span> ' + esc(it.d2name) + '</div>' +
			'<div class="g-meta">' + score + esc(fmtDate(it.startDate)) + '</div>' +
			'<div class="g-meta text-truncate">' + esc(it.competition) + '</div>' +
			'<div class="g-meta"><i class="bi bi-clock me-1"></i>' + esc(fmtDate(it.updated, true)) + ' · ' + bytes(it.bytes) + (it.hidden ? ' · <span class="text-danger">skryté</span>' : '') + '</div>' +
			'<div class="g-actions">' +
			'<a class="btn btn-outline-primary" href="' + esc(it.url) + '" download="' + esc(dl) + '" title="Stiahnuť"><i class="bi bi-download"></i></a>' +
			'<button type="button" class="btn btn-outline-secondary" data-copy="' + esc(ROOT + it.url.split('?')[0]) + '" title="Kopírovať odkaz"><i class="bi bi-link-45deg"></i></button>' +
			(ADMIN ? '' : '<button type="button" class="btn btn-outline-danger" data-hide title="Odstrániť z galérie"><i class="bi bi-eye-slash"></i></button>') +
			'</div></div></div></div>';
	}

	function render() {
		$('#gGrid').html(items.map(card).join(''));
		$('[data-count]').each(function () {
			const t = this.dataset.count;
			this.textContent = t ? items.filter((i) => i.type === t).length : items.length;
		});
		const total = items.reduce((a, i) => a + (i.bytes || 0), 0);
		$('#gSummary').text(items.length + ' obrázkov · spolu ' + bytes(total));
		$('#gEmpty').prop('hidden', items.length > 0);
		apply();
	}

	function apply() {
		let n = 0;
		$('.g-item').each(function () {
			const show = (!type || this.dataset.type === type) && (!q || this.dataset.search.includes(q));
			this.hidden = !show;
			if (show) n++;
		});
		$('.filter-empty').prop('hidden', n > 0 || !items.length);
	}

	async function load() {
		try {
			const r = await fetch('api-gallery' + (ADMIN ? '?admin=' + encodeURIComponent(ADMIN) : ''), { headers: MD.clientHeaders(), cache: 'no-store' });
			const j = await r.json();
			items = j.items || [];
			render();
		} catch (e) {
			$('#gSummary').text('Galériu sa nepodarilo načítať.');
			MD.toast('Galériu sa nepodarilo načítať.', 'danger');
		}
		$('#gLoading').prop('hidden', true);
	}

	$('#gFilter').on('click', '[data-type]', function () {
		type = this.dataset.type;
		$(this).addClass('active').siblings().removeClass('active');
		apply();
	});
	$('#gSearch').on('input', function () { q = this.value.trim().toLowerCase(); apply(); });

	$('#gGrid').on('click', '[data-copy]', function () {
		navigator.clipboard.writeText(this.dataset.copy).then(() => MD.toast('Odkaz skopírovaný'), () => MD.toast('Odkaz sa nepodarilo skopírovať', 'danger'));
	});

	let toHide = null;
	const modal = new bootstrap.Modal('#delModal');
	$('#gGrid').on('click', '[data-hide]', function () { toHide = $(this).closest('.g-item'); modal.show(); });
	$('#delOk').on('click', async function () {
		modal.hide();
		if (!toHide) return;
		const file = toHide.data('file');
		try {
			const r = await fetch('api-gallery', { method: 'POST', headers: MD.clientHeaders({ 'Content-Type': 'application/json' }), body: JSON.stringify({ action: 'hide', file }) });
			const j = await r.json();
			if (!j.ok) throw new Error(j.error || 'chyba');
			items = items.filter((i) => i.file !== file);
			toHide.fadeOut(200, render);
			MD.toast('Obrázok odstránený z galérie');
		} catch (e) {
			MD.toast('Nepodarilo sa odstrániť: ' + e.message, 'danger');
		}
	});

	load();

	if (ADMIN) {
		fetch('api-log?admin=' + encodeURIComponent(ADMIN)).then((r) => r.json()).then((j) => {
			const logs = j.logs || [];
			$('#logList').html(logs.length ? '' : 'zatiaľ prázdny');
			logs.forEach((l) => {
				const u = 'api-log?admin=' + encodeURIComponent(ADMIN) + '&m=' + l.month;
				$('#logList').append(
					'<span class="btn-group btn-group-sm me-1"><a class="btn btn-outline-secondary" target="_blank" href="' + u + '">' + l.month + ' <span class="text-muted-2">(' + bytes(l.bytes) + ')</span></a>' +
					'<a class="btn btn-outline-secondary" href="' + u + '&download=1" title="Stiahnuť"><i class="bi bi-download"></i></a></span>');
			});
		}).catch(() => $('#logList').text('nepodarilo sa načítať'));
	}
});
</script>

<?php add_layout("footer"); ?>
