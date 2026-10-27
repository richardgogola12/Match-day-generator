<?php
/**
 * Všetky zápasy všetkých družstiev klubu v sezóne (pre obrázok Víkend klubu).
 *
 *   GET api-club?club=<klub>&s=<sezóna>  -> { club: {name, logo, id}, season, matches: [ brief + cat ] }
 */
$club = (string)($_GET['club'] ?? '');
$s = (string)($_GET['s'] ?? '');
if (!preg_match('/^[A-Za-z0-9_\-.]{2,120}$/', $club)) json_response(['error' => 'Neplatné ID klubu'], 400);
if (!preg_match('#^\d{4}/\d{4}$#', $s)) $s = md_default_season();
@set_time_limit(90);

$appSpace = str_starts_with($club, 'issf_') || str_contains($club, '.') ? $club : $club . '.futbalnet.sk';
$res = sn_fetch_cached(sprintf(API_CLUB_TEAMS, rawurlencode($appSpace), rawurlencode($s)), 1800);
$teams = array_values((array)($res['teams'] ?? []));
if (!$teams) json_response(['error' => !empty($res['error']) ? 'Družstvá klubu sa nepodarilo načítať zo Sportnetu' : 'Klub nemá v sezóne ' . $s . ' žiadne družstvá'], !empty($res['error']) ? 502 : 404);

$lists = sn_fetch_many(array_map(fn($t) => sprintf(API_MATCHES, rawurlencode((string)$t['_id'])), $teams), 900);
$out = []; $name = (string)($teams[0]['name'] ?? ''); $logo = '';
foreach ($teams as $i => $t) {
    $tid = (string)$t['_id'];
    foreach ((array)($lists[$i]['matches'] ?? []) as $m) {
        if (($m['matchState'] ?? '') === 'NEZUCASTNENE_JEDNO_DRUZSTVO' || empty($m['_id'])) continue;
        $b = sn_match_brief($m);
        $own = $b['home']['id'] === $tid ? 'home' : ($b['away']['id'] === $tid ? 'away' : '');
        if ($own === '') continue;
        if (!$logo) $logo = $b[$own]['logo'];
        $out[$b['id']] = ['id' => $b['id'], 'date' => $b['date'], 'played' => $b['played'], 'round' => $b['round'], 'competition' => $b['competition'],
            'cat' => (string)($t['displayName'] ?? ''), 'teamId' => $tid, 'side' => $own,
            'home' => ['id' => $b['home']['id'], 'name' => $b['home']['name'], 'logo' => $b['home']['logo'], 'score' => $b['home']['score']],
            'away' => ['id' => $b['away']['id'], 'name' => $b['away']['name'], 'logo' => $b['away']['logo'], 'score' => $b['away']['score']],
            'result' => $b['played'] ? sn_brief_result($b, $tid) : ''];
    }
}
$out = array_values($out);
usort($out, fn($a, $b) => strcmp($a['date'], $b['date']));
json_response(['club' => ['id' => sn_club_slug($appSpace), 'name' => $name, 'logo' => $logo], 'season' => $s, 'teams' => array_map(fn($t) => ['id' => (string)$t['_id'], 'cat' => (string)($t['displayName'] ?? '')], $teams), 'matches' => $out]);
