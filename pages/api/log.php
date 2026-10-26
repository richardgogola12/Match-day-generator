<?php
/**
 * Stiahnutie TXT logu generovania – iba s admin heslom (GALLERY_ADMIN_KEY).
 *   GET api-log?admin=HESLO            -> zoznam mesiacov (JSON)
 *   GET api-log?admin=HESLO&m=2026-10  -> TXT súbor
 */
if (!gallery_is_admin($_GET['admin'] ?? null)) json_response(['error' => 'Prístup zamietnutý'], 403);

$m = $_GET['m'] ?? '';
if ($m === '') {
    $months = [];
    foreach (glob(LOG_DIR . 'generovanie-*.txt') ?: [] as $f) {
        if (preg_match('/generovanie-(\d{4}-\d{2})\.txt$/', $f, $x)) $months[] = ['month' => $x[1], 'bytes' => filesize($f)];
    }
    rsort($months);
    json_response(['logs' => $months]);
}
if (!preg_match('/^\d{4}-\d{2}$/', $m) || !is_file($file = LOG_DIR . 'generovanie-' . $m . '.txt')) {
    json_response(['error' => 'Log sa nenašiel'], 404);
}

while (ob_get_level() > 0) ob_end_clean();
header('Content-Type: text/plain; charset=utf-8');
header('Content-Disposition: ' . (isset($_GET['download']) ? 'attachment' : 'inline') . '; filename="generovanie-' . $m . '.txt"');
header('Cache-Control: no-store');
readfile($file);
exit;
