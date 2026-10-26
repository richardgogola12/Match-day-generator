<?php
/**
 * Globálne hľadanie klubov (Sportnet – zoznam klubov SFZ).
 *
 *   GET api-search?q=<text>  -> { clubs: [{id, name, logo}], season: "2025/2026" }
 *
 * Výsledky sa krátko uchovávajú v cache; hľadá sa od 2 znakov.
 */
$q = trim((string)($_GET['q'] ?? ''));
$q = mb_substr(preg_replace('/\s+/u', ' ', $q), 0, 60, 'UTF-8');
if (mb_strlen($q, 'UTF-8') < 2) json_response(['clubs' => [], 'season' => md_default_season()]);

$res = sn_fetch_cached(sprintf(API_CLUB_SEARCH, 12, rawurlencode($q)), 6 * 3600);
$clubs = [];
foreach ((array)($res['items'] ?? []) as $it) {
    $full = (string)($it['_id'] ?? '');
    if ($full === '' || !preg_match('/^[A-Za-z0-9_\-.]{2,120}$/', $full)) continue;
    $clubs[] = [
        'id'   => preg_replace('/\.futbalnet\.sk$/', '', $full),
        'name' => (string)($it['name'] ?? $full),
        'logo' => (string)sn_val($it, ['logo_public_url', 'logo.public_url'], ''),
        'active' => ($it['status'] ?? 'active') === 'active',
    ];
}
// aktívne kluby ako prvé
usort($clubs, fn($a, $b) => (int)$b['active'] <=> (int)$a['active']);
header('Cache-Control: private, max-age=300');
json_response(['clubs' => $clubs, 'season' => md_default_season(), 'error' => !empty($res['error']) && !$clubs]);
