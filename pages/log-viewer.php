<?php
/**
 * Tajný prehliadač logu generovania:  /match-day/log/<LOG_SECRET>[?m=RRRR-MM][&raw=1][&download=1]
 * Pri nesprávnom kľúči sa zobrazí bežná stránka 404 (neprezradí, že niečo také existuje).
 */
$token = segment(2);
if (LOG_SECRET === '' || !is_string($token) || !hash_equals(LOG_SECRET, $token)) show_404();

header('X-Robots-Tag: noindex, nofollow, noarchive');
header('Referrer-Policy: no-referrer');
header('Cache-Control: no-store');

// dostupné mesiace
$months = [];
foreach (glob(LOG_DIR . 'generovanie-*.txt') ?: [] as $f) {
    if (preg_match('/generovanie-(\d{4}-\d{2})\.txt$/', $f, $x)) $months[$x[1]] = filesize($f);
}
krsort($months);

$m = $_GET['m'] ?? (array_key_first($months) ?? date('Y-m'));
if (!preg_match('/^\d{4}-\d{2}$/', $m)) $m = date('Y-m');
$file = LOG_DIR . 'generovanie-' . $m . '.txt';
$content = is_file($file) ? (string)file_get_contents($file) : '';

// surový TXT / stiahnutie
if (isset($_GET['raw']) || isset($_GET['download'])) {
    while (ob_get_level() > 0) ob_end_clean();
    header('Content-Type: text/plain; charset=utf-8');
    header('Content-Disposition: ' . (isset($_GET['download']) ? 'attachment' : 'inline') . '; filename="generovanie-' . $m . '.txt"');
    echo $content !== '' ? $content : "Log za $m je prázdny.\n";
    exit;
}

// rozdelenie na záznamy
$entries = [];
foreach (preg_split('/^={20,}\s*$/m', $content) as $block) {
    $block = trim($block, "\r\n");
    if (!preg_match('/^(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\s+\|\s+(.+?)\s+\|\s+(.+)$/m', $block, $h)) continue;
    $field = function ($label) use ($block) {
        return preg_match('/^\s+' . preg_quote($label, '/') . ':\s+(.+)$/mu', $block, $x) ? trim($x[1]) : '';
    };
    $entries[] = [
        'time' => $h[1], 'type' => $h[2], 'match' => $h[3],
        'ip' => preg_replace('/\s+\(.*$/', '', $field('IP adresa')),
        'ipExtra' => preg_match('/\((.+)\)/', $field('IP adresa'), $x) ? $x[1] : '',
        'client' => $field('ID prehliadača'),
        'browser' => $field('Prehliadač'), 'os' => $field('Systém'), 'device' => $field('Zariadenie'),
        'action' => $field('Akcia'),
        'text' => $block,
    ];
}
$entries = array_reverse($entries); // najnovšie hore

$byType = array_count_values(array_column($entries, 'type'));
$ips = count(array_unique(array_column($entries, 'ip')));
$clients = count(array_unique(array_column($entries, 'client')));
$base = url('log/' . LOG_SECRET);

add_layout("header", array("title" => "Log generovania " . $m, "noindex" => true));
?>

<section class="hero hero-sm">
	<div class="container-xxl">
		<span class="hero-eyebrow mb-3"><i class="bi bi-incognito"></i> Tajná stránka – nezdieľajte odkaz</span>
		<h1 class="mb-2">Log <span class="accent">generovania</span></h1>
		<p class="lead mb-0">Kto, kedy a aký obrázok vygeneroval · <?= plain($m) ?></p>
	</div>
</section>

<div class="container-xxl overlap">
	<div class="card-app mb-4">
		<div class="card-body">
			<div class="row g-3 align-items-end">
				<div class="col-sm-6 col-lg-3">
					<label class="form-label small fw-semibold" for="logMonth">Mesiac</label>
					<select class="form-select" id="logMonth" onchange="location.href='<?= plain($base) ?>?m=' + this.value">
						<?php if (!$months): ?><option><?= plain($m) ?></option><?php endif; ?>
						<?php foreach ($months as $mm => $size): ?>
							<option value="<?= $mm ?>" <?= $mm === $m ? 'selected' : '' ?>><?= $mm ?> (<?= human_bytes($size) ?>)</option>
						<?php endforeach; ?>
					</select>
				</div>
				<div class="col-sm-6 col-lg-5">
					<label class="form-label small fw-semibold" for="logSearch">Hľadať</label>
					<div class="input-group">
						<span class="input-group-text"><i class="bi bi-search"></i></span>
						<input type="search" class="form-control" id="logSearch" placeholder="IP, tím, prehliadač, Windows 11, iPhone…">
					</div>
				</div>
				<div class="col-lg-4 d-flex gap-2 justify-content-lg-end">
					<a class="btn btn-outline-secondary" href="<?= plain($base) ?>?m=<?= $m ?>&raw=1" target="_blank"><i class="bi bi-file-text me-1"></i>Čistý TXT</a>
					<a class="btn btn-primary" href="<?= plain($base) ?>?m=<?= $m ?>&download=1"><i class="bi bi-download me-1"></i>Stiahnuť</a>
				</div>
			</div>

			<div class="row g-3 mt-1">
				<div class="col-6 col-md-3"><div class="stat-tile"><div class="stat-num"><?= count($entries) ?></div><div class="stat-label">obrázkov</div></div></div>
				<div class="col-6 col-md-3"><div class="stat-tile"><div class="stat-num"><?= $ips ?></div><div class="stat-label">rôznych IP adries</div></div></div>
				<div class="col-6 col-md-3"><div class="stat-tile"><div class="stat-num"><?= $clients ?></div><div class="stat-label">rôznych prehliadačov</div></div></div>
				<div class="col-6 col-md-3"><div class="stat-tile">
					<div class="stat-label mb-1">podľa typu</div>
					<?php foreach ($byType as $t => $c): ?><span class="badge text-bg-secondary me-1"><?= plain($t) ?> <?= $c ?></span><?php endforeach; ?>
					<?php if (!$byType): ?><span class="text-muted-2 small">–</span><?php endif; ?>
				</div></div>
			</div>
		</div>
	</div>

	<?php if (!$entries): ?>
		<div class="card-app"><div class="empty-state"><i class="bi bi-journal"></i>Za tento mesiac zatiaľ nie sú žiadne záznamy.</div></div>
	<?php else: ?>
		<div class="card-app overflow-hidden">
			<div class="accordion accordion-flush log-list" id="logList">
				<?php foreach ($entries as $i => $e): ?>
					<div class="accordion-item log-entry" data-search="<?= plain(mb_strtolower($e['text'], 'UTF-8')) ?>">
						<h2 class="accordion-header">
							<button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#le<?= $i ?>">
								<div class="log-row">
									<span class="log-time"><?= plain(date('j.n. H:i:s', strtotime($e['time']))) ?></span>
									<span class="badge rounded-pill text-bg-dark log-type"><?= plain($e['type']) ?></span>
									<span class="log-match"><?= plain($e['match']) ?></span>
									<span class="log-who">
										<span title="IP adresa<?= $e['ipExtra'] ? ' – ' . plain($e['ipExtra']) : '' ?>"><i class="bi bi-globe2 me-1"></i><?= plain($e['ip']) ?></span>
										<span><i class="bi bi-window me-1"></i><?= plain($e['browser']) ?></span>
										<span><i class="bi bi-pc-display me-1"></i><?= plain(preg_replace('/\s*\(verzia.*$/', '', $e['os'])) ?></span>
										<span><i class="bi bi-phone me-1"></i><?= plain($e['device']) ?></span>
									</span>
								</div>
							</button>
						</h2>
						<div id="le<?= $i ?>" class="accordion-collapse collapse" data-bs-parent="#logList">
							<div class="accordion-body p-0"><pre class="log-pre"><?= plain($e['text']) ?></pre></div>
						</div>
					</div>
				<?php endforeach; ?>
			</div>
		</div>
		<div class="card-app mt-3" id="logEmpty" hidden><div class="empty-state"><i class="bi bi-funnel"></i>Nič nezodpovedá hľadaniu.</div></div>
	<?php endif; ?>
</div>

<script>
$(function () {
	$('#logSearch').on('input', function () {
		const q = this.value.trim().toLowerCase();
		let n = 0;
		$('.log-entry').each(function () {
			const show = !q || this.dataset.search.includes(q);
			this.hidden = !show;
			if (show) n++;
		});
		$('#logEmpty').prop('hidden', n > 0);
	});
});
</script>

<?php add_layout("footer"); ?>
