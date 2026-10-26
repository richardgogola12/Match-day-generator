<?php
/**
 * Družstvo a jeho súťaž za sezónu.
 *
 *   GET api-team?team=<teamID>             -> { team, players[], coach, stats, competition: { table, rounds, … } }
 *   GET api-team?team=<teamID>&only=comp   -> iba súťaž (tabuľka, kolá) – rýchlejšie
 */
$team = (string)($_GET['team'] ?? '');
if (!preg_match('/^[A-Za-z0-9_\-.]{2,80}$/', $team)) json_response(['error' => 'Neplatné ID družstva'], 400);
@set_time_limit(120);

$comp = sportnet_competition($team);
if (($_GET['only'] ?? '') === 'comp') {
    if (!empty($comp['error'])) json_response($comp, 502);
    json_response(['competition' => $comp]);
}
$t = sportnet_team($team);
if (!empty($t['error'])) json_response($t, 502);
$t['competitionData'] = empty($comp['error']) ? $comp : null;
json_response($t);
