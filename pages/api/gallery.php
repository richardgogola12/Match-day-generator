<?php
/**
 * Galéria uložených obrázkov – vždy iba obrázky daného prehliadača (hlavička X-MD-Client).
 *
 *   GET  api-gallery                        -> zoznam obrázkov tohto prehliadača
 *   GET  api-gallery?admin=HESLO            -> všetky obrázky (ak je nastavené GALLERY_ADMIN_KEY)
 *   POST api-gallery {action:"hide", file}  -> odstráni obrázok z galérie tohto prehliadača
 *                                              (súbor na serveri ZOSTÁVA)
 */
$client = client_hash();

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $in = json_decode((string)file_get_contents('php://input'), true) ?: $_POST;
    if (!in_array($in['action'] ?? '', ['hide', 'delete'], true)) json_response(['error' => 'Neznáma akcia'], 400);

    $path = gallery_path($in['file'] ?? '');
    $jsonFile = $path ? substr($path, 0, -4) . '.json' : '';
    $meta = $jsonFile && is_file($jsonFile) ? json_decode((string)file_get_contents($jsonFile), true) : null;
    if (!is_array($meta) || $client === '' || ($meta['client'] ?? '') !== $client) {
        json_response(['error' => 'Obrázok sa nenašiel'], 404);
    }

    $meta['hidden'] = true;
    $meta['hiddenAt'] = (new DateTime('now', new DateTimeZone('Europe/Bratislava')))->format('c');
    file_put_contents($jsonFile, json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
    json_response(['ok' => true]);
}

if (gallery_is_admin($_GET['admin'] ?? null)) {
    json_response(['items' => gallery_items(null), 'admin' => true]);
}
if ($client === '') json_response(['items' => []]);

json_response(['items' => gallery_items($client)]);
