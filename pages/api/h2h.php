<?php
/**
 * Vzájomné zápasy, forma oboch družstiev a tabuľka k zápasu (pre generátor a stránku zápasu).
 *
 *   GET api-h2h?id=<matchID>   -> { brief, h2h: { played, home, away, matches[] }, form: { home[], away[] }, table[] }
 */
$id = (string)($_GET['id'] ?? '');
if (!preg_match('/^[A-Za-z0-9_\-]{6,64}$/', $id)) json_response(['error' => 'Neplatné ID zápasu'], 400);
@set_time_limit(90);

$doc = sn_fetch_cached(sprintf(API_MATCH, rawurlencode($id)), 600);
if (!is_array($doc) || !empty($doc['error'])) json_response(['error' => 'Zápis sa nepodarilo načítať zo Sportnetu'], 502);
$b = md_brief_from_doc($doc);
$slim = fn($m) => ['id' => $m['id'], 'date' => $m['date'], 'round' => $m['round'], 'competition' => $m['competition'], 'result' => $m['result'] ?? '',
    'home' => ['id' => $m['home']['id'], 'name' => $m['home']['name'], 'logo' => $m['home']['logo'], 'score' => $m['home']['score']],
    'away' => ['id' => $m['away']['id'], 'name' => $m['away']['name'], 'logo' => $m['away']['logo'], 'score' => $m['away']['score']]];
$h2h = sportnet_h2h($b);
$h2h['matches'] = array_map($slim, $h2h['matches']);
json_response([
    'brief' => $b,
    'h2h' => $h2h,
    'form' => [
        'home' => array_map($slim, sportnet_form($b['home']['id'], $b['date'], 5, $b['id'])),
        'away' => array_map($slim, sportnet_form($b['away']['id'], $b['date'], 5, $b['id'])),
    ],
]);
