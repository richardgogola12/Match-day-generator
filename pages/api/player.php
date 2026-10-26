<?php
/**
 * Hráč zo Sportnetu (meno, fotka, rok narodenia) – pre generátor.
 *   GET api-player?id=<sportnetId>  -> { id, name, photo, birthyear, age }
 */
$u = sportnet_user((string)($_GET['id'] ?? ''));
if (!$u) json_response(['error' => 'Hráč sa nenašiel'], 404);
header('Cache-Control: private, max-age=3600');
json_response(['id' => $u['id'], 'name' => $u['name'], 'photo' => $u['photo'] ? url('api-logo') . '?raw=1&url=' . rawurlencode($u['photo']) : '', 'birthyear' => $u['birthyear'], 'age' => $u['age']]);
