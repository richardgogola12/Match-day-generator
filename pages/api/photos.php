<?php
/**
 * Fotky hráčov zo Sportnetu naraz (súpiska družstva).
 *   GET api-photos?ids=id1,id2,…  (najviac 40)  -> { photos: { id: url } }
 */
$ids = array_values(array_unique(array_filter(explode(',', (string)($_GET['ids'] ?? '')), fn($i) => preg_match('/^[A-Za-z0-9_\-]{6,64}$/', $i))));
$ids = array_slice($ids, 0, 40);
if (!$ids) json_response(['photos' => []]);
$res = sn_fetch_many(array_map(fn($i) => sprintf(API_USER, rawurlencode($i)), $ids), 24 * 3600);
$out = [];
foreach ($ids as $n => $id) {
    $u = $res[$n] ?? null;
    if (!is_array($u)) continue;
    foreach (['photo', 'formalPhoto'] as $k) if (!empty($u[$k]['public_url']) && ($u[$k]['public'] ?? true)) { $out[$id] = (string)$u[$k]['public_url']; break; }
}
header('Cache-Control: public, max-age=3600');
json_response(['photos' => $out]);
