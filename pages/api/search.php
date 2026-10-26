<?php
/**
 * Globálne hľadanie klubov a hráčov (Sportnet).
 *
 *   GET api-search?q=<text>  -> { clubs: [{id, name, logo, active}], players: [{id, name, birthyear, photo}], season: "2025/2026" }
 *
 * Výsledky sa uchovávajú v cache; kluby sa hľadajú od 2 znakov, hráči od 3 znakov.
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
// hráči (registrovaní hráči SFZ); Sportnet hľadá s diakritikou
$players = [];
if (mb_strlen($q, 'UTF-8') >= 3) {
    $pr = sn_fetch_cached(sprintf(API_PLAYER_SEARCH, rawurlencode($q), 8), 6 * 3600);
    foreach ((array)($pr['users'] ?? []) as $u) {
        $id = (string)($u['_id'] ?? '');
        if (!preg_match('/^[A-Za-z0-9_\-]{6,64}$/', $id)) continue;
        $isPlayer = false;
        foreach ((array)($u['sport_orgs'] ?? []) as $o) if (($o['competence_type'] ?? '') === 'player') $isPlayer = true;
        if (!$isPlayer) continue;
        $photo = (string)($u['photo']['public_url'] ?? $u['formalPhoto']['public_url'] ?? '');
        $players[] = ['id' => $id, 'name' => trim(($u['name'] ?? '') . ' ' . ($u['surname'] ?? '')), 'birthyear' => (int)($u['birthyear'] ?? 0) ?: null,
                      'photo' => $photo !== '' ? url('api-logo') . '?raw=1&url=' . rawurlencode($photo) : ''];
    }
}
header('Cache-Control: private, max-age=300');
json_response(['clubs' => $clubs, 'players' => $players, 'season' => md_default_season(), 'error' => !empty($res['error']) && !$clubs && !$players]);
