<?php
/**
 * Galéria uložených obrázkov.
 *
 *   GET  api-gallery                          -> obrázky tohto prehliadača (hlavička X-MD-Client)
 *   GET  api-gallery?all=1                    -> všetky obrázky (iba prihlásený správca)
 *   POST api-gallery {action:"hide", file}    -> skryje obrázok z galérie tohto prehliadača
 *                                                (súbor na serveri ZOSTÁVA)
 *   POST api-gallery {action:"delete", file}  -> natrvalo zmaže obrázok zo servera
 *                                                (iba správca, hlavička X-CSRF-Token)
 */
$client = client_hash();
$admin  = is_admin();
if ($admin) no_store_headers();

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $in = json_decode((string)file_get_contents('php://input'), true) ?: $_POST;
    $action = $in['action'] ?? '';
    if (!in_array($action, ['hide', 'delete'], true)) json_response(['error' => 'Neznáma akcia'], 400);

    $path = gallery_path($in['file'] ?? '');
    $jsonFile = $path ? substr($path, 0, -4) . '.json' : '';
    $meta = $jsonFile && is_file($jsonFile) ? json_decode((string)file_get_contents($jsonFile), true) : null;
    if (!is_array($meta)) json_response(['error' => 'Obrázok sa nenašiel'], 404);

    if ($action === 'delete') {
        if (!$admin) json_response(['error' => 'Prístup zamietnutý'], 403);
        if (!csrf_ok($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null)) json_response(['error' => 'Neplatný token, obnovte stránku'], 403);
        if (!gallery_delete($path)) json_response(['error' => 'Súbor sa nepodarilo zmazať'], 500);
        json_response(['ok' => true]);
    }

    // hide – iba vlastník (prehliadač, ktorý obrázok vytvoril)
    if ($client === '' || ($meta['client'] ?? '') !== $client) json_response(['error' => 'Obrázok sa nenašiel'], 404);
    $meta['hidden'] = true;
    $meta['hiddenAt'] = (new DateTime('now', new DateTimeZone('Europe/Bratislava')))->format('c');
    file_put_contents($jsonFile, json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
    json_response(['ok' => true]);
}

if (!empty($_GET['all'])) {
    if (!$admin) json_response(['error' => 'Prístup zamietnutý'], 403);
    json_response(['items' => gallery_items(null, $client), 'admin' => true]);
}
if ($client === '') json_response(['items' => [], 'admin' => $admin]);

json_response(['items' => gallery_items($client, $client), 'admin' => $admin]);
