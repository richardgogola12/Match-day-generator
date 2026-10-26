<?php
/**
 * Údaje zo zápisu o stretnutí pre generátor v prehliadači.
 *
 *   GET api-match?id=<matchID>&home=<team1ID>&away=<team2ID>
 *
 * Vracia:
 *   goals:   [ { minute, name, side: "home"|"away", own: bool } ]   (vlastný gól sa pripíše súperovi)
 *   cards:   [ { minute, name, side, kind: "yellow"|"red" } ]
 *   subs:    [ { minute, out, in, side } ]
 *   report:  { halftime, spectators, referee, officials: [ {role, name} ], venue }
 *   lineups: { home: { start: [ {nr, name, captain, gk} ], subs: [...], coach }, away: {...} }
 *
 * Správca: &debug=1 vráti skrátenú surovú štruktúru zápisu (na kontrolu formátu dát Sportnetu).
 */
$id   = $_GET['id'] ?? '';
$home = (string)($_GET['home'] ?? '');
$away = (string)($_GET['away'] ?? '');

if (!preg_match('/^[A-Za-z0-9_\-]{6,64}$/', $id)) {
    json_response(['error' => 'Neplatné ID zápasu'], 400);
}

$data = sn_fetch_cached(sprintf(API_MATCH, rawurlencode($id)), 120);

if (!is_array($data) || !empty($data['error'])) {
    json_response(['error' => 'Sportnet API je nedostupné alebo vrátilo chybu'], 502);
}

if (!empty($_GET['debug']) && is_admin()) {
    no_store_headers();
    $short = function ($v, $depth = 0) use (&$short) {
        if (!is_array($v)) return is_string($v) ? mb_substr($v, 0, 80, 'UTF-8') : $v;
        if ($depth > 4) return '…';
        $out = [];
        $n = 0;
        foreach ($v as $k => $x) {
            if (++$n > (array_is_list($v) ? 2 : 40)) { $out['…'] = count($v) . ' položiek'; break; }
            $out[$k] = $short($x, $depth + 1);
        }
        return $out;
    };
    json_response(['keys' => array_keys($data), 'nominations' => $short($data['nominations'] ?? null), 'protocol' => $short($data['protocol'] ?? null),
        'other' => $short(array_diff_key($data, ['nominations' => 1, 'protocol' => 1])), 'parsed' => ['lineups' => sportnet_lineups($data, $home, $away), 'events' => sportnet_events($data, $home), 'report' => sportnet_report($data)]]);
}

$strip = fn($list) => array_map(fn($p) => ['nr' => $p['nr'], 'name' => $p['name'], 'captain' => $p['captain'], 'gk' => $p['gk']], $list);
$lineups = sportnet_lineups($data, $home, $away);
foreach ($lineups as $s => $l) {
    $lineups[$s] = ['start' => $strip($l['start']), 'subs' => $strip($l['subs']), 'coach' => $l['coach']];
}

$events = sportnet_events($data, $home);
json_response([
    'goals'   => array_values(array_map(fn($g) => ['minute' => (string)$g['minute'], 'name' => $g['name'], 'side' => $g['side'], 'own' => $g['own'], 'penalty' => !empty($g['penalty'])],
                 array_filter($events, fn($e) => $e['kind'] === 'goal'))) ?: array_map(fn($g) => ['minute' => $g['minute'], 'name' => $g['name'], 'side' => $g['side'], 'own' => $g['own']], sportnet_goals($data, $home)),
    'cards'   => array_values(array_map(fn($e) => ['minute' => (string)$e['minute'], 'name' => $e['name'], 'side' => $e['side'], 'kind' => $e['kind']], array_filter($events, fn($e) => in_array($e['kind'], ['yellow', 'red'], true)))),
    'subs'    => array_values(array_map(fn($e) => ['minute' => (string)$e['minute'], 'out' => $e['name'], 'in' => $e['in'], 'side' => $e['side']], array_filter($events, fn($e) => $e['kind'] === 'sub'))),
    'report'  => sportnet_report($data),
    'lineups' => $lineups,
]);
