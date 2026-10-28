<?php
/**
 * Nastavenia aplikácie (iba správca): /match-day/nastavenia-spravcu
 * Hodnoty sa ukladajú do storage/private/settings.json; config.local.php má prednosť (pole je potom uzamknuté).
 */
if (!is_admin()) redirect('spravca?dalej=nastavenia-spravcu', 303);
no_store_headers();

$schema = md_settings_schema();
$groups = md_settings_groups();
$saved = md_settings_saved();
$errors = [];
$notice = !empty($_GET['ulozene']) ? ['success', 'Nastavenia sú uložené.'] : null;

if (is_post_request()) {
    if (!csrf_ok($_POST['csrf'] ?? null)) {
        $errors['_csrf'] = 'Formulár vypršal, skúste to znova.';
    } elseif (($_POST['action'] ?? '') === 'save') {
        [$ok, $errors] = md_settings_save($_POST);
        if ($ok) redirect('nastavenia-spravcu?ulozene=1' . (!empty($_POST['tab']) && isset($groups[$_POST['tab']]) ? '#' . $_POST['tab'] : ''), 303);
        $saved = array_merge($saved, array_intersect_key($_POST, $schema)); // vrátiť zadané hodnoty do formulára
    } elseif (($_POST['action'] ?? '') === 'mailtest') {
        $to = trim((string)($_POST['to'] ?? '')) ?: ADMIN_EMAIL;
        if (!md_rate_limit('mailtest|' . md_ip_hash(), 10, 3600)) $notice = ['danger', 'Príliš veľa skúšobných e-mailov – skúste to o hodinu.'];
        else {
            [$ok, $info] = md_send_mail($to, APP_NAME . ': skúšobný e-mail', "Toto je skúšobný e-mail z aplikácie " . APP_NAME . " (" . APP_URL . ").\n\nAk ho čítate, odosielanie upozornení funguje.\n\nOdoslané: " . date('j. n. Y H:i'));
            $notice = [$ok ? 'success' : 'danger', ($ok ? 'Skúšobný e-mail odoslaný na ' . $to . '. ' : 'E-mail sa nepodarilo odoslať. ') . $info];
        }
    }
}

/** aktuálna (platná) hodnota nastavenia */
$current = function ($key) use ($schema) {
    if (defined($key)) {
        $v = constant($key);
        if ($key === 'SMTP_SECURE') return $v === '' ? 'none' : $v;
        return $v;
    }
    return $schema[$key][1];
};

$field = function ($key, $def) use ($saved, $errors, $current) {
    [$type, $default, $label, $help] = $def;
    $opts = $def[5] ?? [];
    $locked = md_settings_locked($key);
    $id = 'set_' . strtolower($key);
    $err = $errors[$key] ?? '';
    $has = array_key_exists($key, $saved);
    $val = $has ? $saved[$key] : '';
    $dis = $locked ? ' disabled' : '';
    $inv = $err ? ' is-invalid' : '';
    ob_start(); ?>
	<div class="set-row<?= $locked ? ' is-locked' : '' ?>">
		<div class="set-label">
			<label class="fw-semibold" for="<?= $id ?>"><?= plain($label) ?></label>
			<?php if ($locked): ?><span class="badge text-bg-secondary ms-1" title="Hodnota je nastavená v súbore config.local.php a má prednosť"><i class="bi bi-lock-fill me-1"></i>config.local.php</span><?php endif; ?>
			<?php if ($help): ?><div class="small text-muted-2"><?= plain($help) ?></div><?php endif; ?>
			<div class="set-key"><code><?= $key ?></code></div>
		</div>
		<div class="set-input">
		<?php if ($type === 'bool'): $on = $locked ? (bool)$current($key) : ($has ? (bool)$val : (bool)$default); ?>
			<input type="hidden" name="<?= $key ?>" value="0">
			<div class="form-check form-switch mb-0">
				<input class="form-check-input" type="checkbox" role="switch" id="<?= $id ?>" name="<?= $key ?>" value="1"<?= $on ? ' checked' : '' ?><?= $dis ?>>
				<label class="form-check-label small" for="<?= $id ?>"><?= $on ? 'Zapnuté' : 'Vypnuté' ?></label>
			</div>
		<?php elseif ($type === 'select'): $sel = $locked ? (string)$current($key) : (string)($has ? $val : $default); ?>
			<select class="form-select<?= $inv ?>" id="<?= $id ?>" name="<?= $key ?>"<?= $dis ?>>
				<?php foreach ($opts as $v => $l): ?><option value="<?= plain($v) ?>"<?= (string)$v === $sel ? ' selected' : '' ?>><?= plain($l) ?></option><?php endforeach; ?>
			</select>
		<?php elseif ($type === 'list'): $text = $locked ? (string)$current($key) : ($has ? (string)$val : ''); ?>
			<textarea class="form-control font-monospace small<?= $inv ?>" id="<?= $id ?>" name="<?= $key ?>" rows="5" placeholder="<?= plain(str_replace(',', "\n", (string)$default)) ?>"<?= $dis ?>><?= plain(str_replace(',', "\n", $text)) ?></textarea>
			<?php if (!$locked && !$has): ?><div class="form-text">Prázdne = predvolený zoznam.</div><?php endif; ?>
		<?php elseif ($type === 'secret'): $isSet = $locked ? constant($key) !== '' : ($has && $val !== ''); ?>
			<div class="input-group">
				<input type="password" class="form-control<?= $inv ?>" id="<?= $id ?>" name="<?= $key ?>" autocomplete="new-password" placeholder="<?= $isSet ? '•••••••• (nastavené – nechajte prázdne, ak nechcete meniť)' : 'nenastavené' ?>"<?= $dis ?>>
				<?php if ($key === 'CRON_KEY' && !$locked): ?><button class="btn btn-outline-secondary" type="button" data-gen="<?= $id ?>" title="Vygenerovať náhodný kľúč"><i class="bi bi-shuffle"></i></button><?php endif; ?>
				<button class="btn btn-outline-secondary" type="button" data-show="<?= $id ?>" title="Zobraziť"<?= $dis ?>><i class="bi bi-eye"></i></button>
			</div>
			<?php if ($isSet && !$locked): ?><div class="form-check mt-1"><input class="form-check-input" type="checkbox" id="<?= $id ?>_clear" name="<?= $key ?>__clear" value="1"><label class="form-check-label small" for="<?= $id ?>_clear">Vymazať</label></div><?php endif; ?>
		<?php else: $text = $locked ? (string)$current($key) : ($has ? (string)$val : ''); $min = $opts[0] ?? null; $max = $opts[1] ?? null; ?>
			<input type="<?= $type === 'int' ? 'number' : ($type === 'email' ? 'email' : 'text') ?>" class="form-control<?= $inv ?>" id="<?= $id ?>" name="<?= $key ?>" value="<?= plain($text) ?>"
				placeholder="<?= plain($default === '' ? 'nenastavené' : 'predvolené: ' . (is_bool($default) ? ($default ? 'áno' : 'nie') : $default)) ?>"<?= $type === 'int' && $min !== null ? ' min="' . (int)$min . '" max="' . (int)$max . '"' : '' ?><?= $dis ?>>
		<?php endif; ?>
		<?php if ($err): ?><div class="invalid-feedback d-block"><?= plain($err) ?></div><?php endif; ?>
		</div>
	</div>
	<?php return ob_get_clean();
};

$writable = is_writable(PRIVATE_DIR) || (!is_dir(PRIVATE_DIR) && is_writable(dirname(PRIVATE_DIR)));
$cronUrl = CRON_KEY !== '' ? APP_URL . '/cron?key=' . rawurlencode(CRON_KEY) : '';

add_layout("header", array("title" => "Nastavenia | Správca | Match Day", "noindex" => true));
?>

<section class="hero hero-sm">
	<div class="container-xxl">
		<a href="<?= url('spravca') ?>" class="btn btn-sm btn-outline-light rounded-pill mb-3"><i class="bi bi-arrow-left me-1"></i> Správca</a>
		<h1 class="mb-2">Nastavenia <span class="accent">aplikácie</span></h1>
		<p class="lead mb-0">Všetko, čo sa dalo nastaviť iba v config.local.php, upravíte tu. Ukladá sa na server do chráneného priečinka.</p>
	</div>
</section>

<div class="container-xxl overlap">
	<?php if ($notice): ?><div class="alert alert-<?= $notice[0] ?>"><i class="bi <?= $notice[0] === 'success' ? 'bi-check-circle' : 'bi-exclamation-triangle' ?> me-1"></i><?= plain($notice[1]) ?></div><?php endif; ?>
	<?php if ($errors): ?><div class="alert alert-danger"><i class="bi bi-exclamation-triangle me-1"></i>Nastavenia sa neuložili: <?= plain(implode(' ', $errors)) ?></div><?php endif; ?>
	<?php if (!$writable): ?><div class="alert alert-warning"><i class="bi bi-folder-x me-1"></i>Priečinok <code>storage/private</code> nie je zapisovateľný pre PHP – nastavenia sa nedajú uložiť.</div><?php endif; ?>

	<div class="row g-4">
		<div class="col-lg-3 d-none d-lg-block">
			<nav class="card-app set-nav"><div class="card-body p-2">
				<?php foreach ($groups as $g => [$ic, $name]): ?><a class="set-nav-link" href="#<?= $g ?>"><i class="bi <?= $ic ?>"></i><?= plain($name) ?></a><?php endforeach; ?>
				<a class="set-nav-link" href="#info"><i class="bi bi-info-circle"></i>Systém</a>
			</div></nav>
		</div>
		<div class="col-lg-9">
			<form method="post" action="<?= url('nastavenia-spravcu') ?>" id="setForm" autocomplete="off">
				<input type="hidden" name="action" value="save"><input type="hidden" name="csrf" value="<?= plain(csrf_token()) ?>"><input type="hidden" name="tab" id="setTab" value="">
				<?php foreach ($groups as $g => [$ic, $name]): ?>
					<section class="card-app mb-4 set-card" id="<?= $g ?>">
						<div class="card-header sec-head"><h2 class="team-title mb-0"><i class="bi <?= $ic ?> me-2"></i><?= plain($name) ?></h2></div>
						<div class="card-body">
							<?php foreach ($schema as $key => $def) if ($def[4] === $g) echo $field($key, $def); ?>
							<?php if ($g === 'mail'): ?>
								<div class="set-extra">
									<div class="small text-muted-2 mb-2">Aktuálne: <?= SMTP_HOST !== '' ? 'SMTP <strong>' . plain(SMTP_HOST) . ':' . (int)SMTP_PORT . '</strong> (' . plain(SMTP_SECURE ?: 'bez šifrovania') . ')' : 'funkcia <code>mail()</code> servera' ?> · odosielateľ <?= plain(md_mail_from()) ?></div>
									<div class="d-flex flex-wrap gap-2">
										<input type="email" class="form-control form-control-sm" style="max-width:16rem" form="mailForm" name="to" value="<?= plain(ADMIN_EMAIL) ?>" placeholder="vas@email.sk" aria-label="Príjemca skúšobného e-mailu">
										<button class="btn btn-sm btn-outline-primary" type="submit" form="mailForm"><i class="bi bi-send me-1"></i>Poslať skúšobný e-mail</button>
									</div>
									<div class="form-text">Skúšobný e-mail sa pošle s <strong>uloženými</strong> nastaveniami – zmeny najprv uložte.</div>
								</div>
								<details class="set-help mt-3"><summary class="small fw-semibold">Nastavenia bežných poskytovateľov</summary>
									<table class="table table-sm small mt-2 mb-0"><thead><tr><th>Poskytovateľ</th><th>Server</th><th>Port</th><th>Poznámka</th></tr></thead><tbody>
										<tr><td>Websupport</td><td><code>smtp.websupport.sk</code></td><td>465</td><td>e-mail a heslo schránky</td></tr>
										<tr><td>Gmail</td><td><code>smtp.gmail.com</code></td><td>465</td><td>heslo aplikácie (Google účet → Zabezpečenie), odosielateľ = účet</td></tr>
										<tr><td>Seznam</td><td><code>smtp.seznam.cz</code></td><td>465</td><td>e-mail a heslo schránky</td></tr>
										<tr><td>Microsoft 365 / Outlook</td><td><code>smtp.office365.com</code></td><td>587</td><td>STARTTLS</td></tr>
									</tbody></table>
								</details>
							<?php endif; ?>
							<?php if ($g === 'cron'): ?>
								<div class="set-extra">
									<div class="small fw-semibold mb-1">Spustenie z cronu (odporúčané) – každú noc o 4:10:</div>
									<code class="d-block set-code mb-3">10 4 * * *  php <?= plain(APP_DIR) ?>/cli/cron.php &gt; /dev/null 2&gt;&amp;1</code>
									<div class="small fw-semibold mb-1">Webcron hostingu (adresa na zavolanie):</div>
									<?php if ($cronUrl): ?>
										<div class="input-group input-group-sm"><input class="form-control font-monospace" value="<?= plain($cronUrl) ?>" readonly id="cronUrl"><button class="btn btn-outline-secondary" type="button" data-copy="cronUrl"><i class="bi bi-clipboard me-1"></i>Kopírovať</button></div>
									<?php else: ?><div class="small text-muted-2">Najprv nastavte a uložte kľúč pre webcron.</div><?php endif; ?>
								</div>
							<?php endif; ?>
						</div>
					</section>
				<?php endforeach; ?>

				<div class="set-save">
					<span class="small text-muted-2 me-auto d-none d-sm-inline"><?= !empty($saved['_updated']) ? 'Naposledy uložené ' . plain(date('j. n. Y H:i', strtotime($saved['_updated']))) : 'Zatiaľ sa používajú predvolené hodnoty' ?></span>
					<button class="btn btn-primary btn-lg" type="submit"<?= $writable ? '' : ' disabled' ?>><i class="bi bi-check2 me-1"></i>Uložiť nastavenia</button>
				</div>
			</form>
			<form method="post" action="<?= url('nastavenia-spravcu') ?>#mail" id="mailForm"><input type="hidden" name="action" value="mailtest"><input type="hidden" name="csrf" value="<?= plain(csrf_token()) ?>"></form>

			<section class="card-app mt-4" id="info">
				<div class="card-header sec-head"><h2 class="team-title mb-0"><i class="bi bi-info-circle me-2"></i>Systém</h2></div>
				<div class="card-body">
					<dl class="kv mb-0">
						<dt>Adresa aplikácie</dt><dd><code><?= plain(APP_URL) ?>/</code> <span class="small text-muted-2">(BASE_PATH v config.php)</span></dd>
						<dt>PHP</dt><dd><?= plain(PHP_VERSION) ?> · <?= function_exists('imagewebp') ? 'GD s WebP' : 'GD bez WebP' ?> · <?= function_exists('fastcgi_finish_request') ? 'PHP-FPM (obnova cache na pozadí)' : (function_exists('litespeed_finish_request') ? 'LiteSpeed (obnova cache na pozadí)' : 'bez FPM') ?></dd>
						<dt>Zapisovateľné</dt><dd><?php foreach (['storage/generated' => STORAGE_DIR, 'storage/logs' => LOG_DIR, 'storage/cache' => CACHE_DIR, 'storage/private' => PRIVATE_DIR] as $n => $d): $w = is_dir($d) && is_writable($d); ?><span class="badge text-bg-<?= $w ? 'success' : 'danger' ?> me-1"><i class="bi bi-<?= $w ? 'check' : 'x' ?>-lg me-1"></i><?= $n ?></span><?php endforeach; ?></dd>
						<dt>Heslo správcu</dt><dd><a href="<?= url('spravca') ?>#heslo">Zmeniť v prehľade správcu</a></dd>
					</dl>
				</div>
			</section>
		</div>
	</div>
</div>

<script>
$(function () {
	$('[data-show]').on('click', function () { const i = document.getElementById(this.dataset.show); i.type = i.type === 'password' ? 'text' : 'password'; $(this).find('i').attr('class', 'bi ' + (i.type === 'password' ? 'bi-eye' : 'bi-eye-slash')); });
	$('[data-gen]').on('click', function () {
		const b = new Uint8Array(18); crypto.getRandomValues(b);
		const i = document.getElementById(this.dataset.gen); i.value = Array.from(b, (x) => x.toString(16).padStart(2, '0')).join(''); i.type = 'text';
	});
	$('[data-copy]').on('click', function () { navigator.clipboard.writeText(document.getElementById(this.dataset.copy).value).then(() => MD.toast('Skopírované')); });
	$('.form-check-input[role=switch]').on('change', function () { $(this).next('label').text(this.checked ? 'Zapnuté' : 'Vypnuté'); });
	// po uložení zostať v rovnakej časti
	$('#setForm').on('submit', function () {
		let tab = '';
		$('.set-card').each(function () { if (this.getBoundingClientRect().top < window.innerHeight / 2) tab = this.id; });
		$('#setTab').val(tab);
	});
	// neuložené zmeny
	let dirty = false;
	$('#setForm').on('input change', () => { dirty = true; }).on('submit', () => { dirty = false; });
	window.addEventListener('beforeunload', (e) => { if (dirty) { e.preventDefault(); e.returnValue = ''; } });
});
</script>

<?php add_layout("footer"); ?>
