<?php
/**
 * Skupiny pre panel „Vytvoriť obrázok“ na úvodnej stránke (obľúbený klub).
 *   GET api-create?club=<klub>&s=<sezóna>  -> { groups: [ klub, najbližšie a posledné zápasy družstiev ] }
 */
$club = (string)($_GET['club'] ?? '');
$s = (string)($_GET['s'] ?? '');
if (!preg_match('/^[A-Za-z0-9_\-.]{2,120}$/', $club)) json_response(['error' => 'Neplatné ID klubu'], 400);
if (!preg_match('#^\d{4}/\d{4}$#', $s)) $s = md_default_season();
@set_time_limit(90);

$appSpace = str_starts_with($club, 'issf_') || str_contains($club, '.') ? $club : $club . '.futbalnet.sk';
$res = sn_fetch_cached(sprintf(API_CLUB_TEAMS, rawurlencode($appSpace), rawurlencode($s)), 1800);
$teams = array_values((array)($res['teams'] ?? []));
if (!$teams) json_response(['groups' => []]);
$lists = sn_fetch_many(array_map(fn($t) => sprintf(API_MATCHES, rawurlencode((string)$t['_id'])), $teams), 900);

$name = ''; $logo = ''; $next = []; $last = [];
foreach ($teams as $i => $t) {
    $tid = (string)$t['_id'];
    $cat = (string)($t['displayName'] ?? '');
    $up = null; $done = null;
    foreach ((array)($lists[$i]['matches'] ?? []) as $m) {
        if (($m['matchState'] ?? '') === 'NEZUCASTNENE_JEDNO_DRUZSTVO' || empty($m['_id'])) continue;
        $b = sn_match_brief($m);
        $own = $b['home']['id'] === $tid ? 'home' : ($b['away']['id'] === $tid ? 'away' : '');
        if ($own === '') continue;
        if (!$name) { $name = $b[$own]['name']; $logo = $b[$own]['logo']; }
        if ($b['played']) { if (!$done || $b['date'] > $done['date']) $done = $b; }
        elseif (strtotime($b['date']) > time() - 3 * 3600 && (!$up || $b['date'] < $up['date'])) $up = $b;
    }
    $ctx = ['myTeam' => $tid, 'season' => $s, 'category' => $cat];
    if ($up) $next[] = [$up['date'], md_create_match($up, $ctx)];
    if ($done) $last[] = [$done['date'], md_create_match($done, $ctx)];
}
usort($next, fn($a, $b) => strcmp($a[0], $b[0]));
usort($last, fn($a, $b) => strcmp($b[0], $a[0]));
$groups = array_merge([md_create_club(sn_club_slug($appSpace), $name, $logo, $s)], array_column(array_slice($next, 0, 4), 1), array_column(array_slice($last, 0, 4), 1));
json_response(['groups' => array_values(array_filter($groups))]);
