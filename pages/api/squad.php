<?php
/**
 * Súpiska družstva za sezónu (zo zápisov odohratých zápasov).
 *
 *   GET api-squad?team=<teamID>
 *
 * Vracia: { players: [ {nr, name, apps, starts, goals, gk, captain} ], coach, team, matches, played }
 */
$team = (string)($_GET['team'] ?? '');
if (!preg_match('/^[A-Za-z0-9_\-.]{2,80}$/', $team)) json_response(['error' => 'Neplatné ID družstva'], 400);

@set_time_limit(90);
$res = sportnet_squad($team);
if (!empty($res['error'])) json_response($res, 502);
json_response($res);
