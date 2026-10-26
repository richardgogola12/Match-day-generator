<?php
/**
 * Góly zápasu pre generátor v prehliadači.
 *
 *   GET api-match?id=<matchID>&home=<team1ID>
 *
 * Vracia: { goals: [ { minute, name, side: "home"|"away", own: bool } ] }
 * Vlastný gól sa pripíše súperovi (rovnako ako pôvodný generate-goals.php).
 */
$id   = $_GET['id'] ?? '';
$home = $_GET['home'] ?? '';

if (!preg_match('/^[A-Za-z0-9_\-]{6,64}$/', $id)) {
    json_response(['error' => 'Neplatné ID zápasu'], 400);
}

try {
    $data = getJsonFromUrl(sprintf(API_MATCH, rawurlencode($id)));
} catch (Throwable $e) {
    json_response(['error' => 'Sportnet API je nedostupné: ' . $e->getMessage()], 502);
}

if (!is_array($data)) {
    json_response(['error' => 'Sportnet API vrátilo neočakávanú odpoveď'], 502);
}
if (!empty($data['error'])) {
    json_response(['error' => 'Sportnet API vrátilo chybu (HTTP ' . ($data['http_code'] ?? '?') . ')'], 502);
}

$events = $data['protocol']['events'] ?? [];
if (!is_array($events)) $events = [];
$goals = [];

foreach ($events as $e) {
    if (!is_array($e)) continue;
    if (($e['eventType'] ?? '') !== 'goal') continue;

    $own  = ($e['type'] ?? '') === 'dropped';
    $isHomeTeam = ($e['team'] ?? '') === $home;
    $side = $isHomeTeam ? 'home' : 'away';
    if ($own) $side = $side === 'home' ? 'away' : 'home';

    $goals[] = [
        'minute' => explode(':', (string)($e['eventTime'] ?? ''))[0],
        'name'   => is_array($e['player'] ?? null) ? ($e['player']['name'] ?? '') : (string)($e['player'] ?? ''),
        'side'   => $side,
        'own'    => $own,
    ];
}

// zoradenie podľa minúty (stabilné)
usort($goals, fn($a, $b) => (int)$a['minute'] <=> (int)$b['minute']);

json_response(['goals' => $goals]);
