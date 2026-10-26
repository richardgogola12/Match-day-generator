<?php
/**
 * Uloží vygenerovaný obrázok na server.
 *
 *   POST api-save?type=matchday|result|goals&d1name=…&d2name=…&startDate=…&competition=…&round=…&score1=…&score2=…&matchID=…
 *   telo požiadavky = samotný PNG (Content-Type: image/png)
 *
 * Hlavička X-MD-Client = anonymné ID prehliadača (obrázok sa zobrazí v galérii iba tomuto prehliadaču).
 *
 * PNG sa posiela priamo ako telo (nie cez formulár), takže neplatí limit upload_max_filesize (2 MB),
 * iba post_max_size. Ten istý zápas a typ z toho istého prehliadača sa ukladá do toho istého súboru (prepíše sa);
 * z iného prehliadača vznikne samostatný súbor – na serveri sú teda všetky.
 */
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    json_response(['error' => 'Použite POST'], 405);
}

$client = client_hash();
if ($client === '') json_response(['error' => 'Chýba identifikátor prehliadača'], 400);

$types = image_types();
$type = $_GET['type'] ?? '';
if (!isset($types[$type])) json_response(['error' => 'Neznámy typ obrázka'], 400);

$raw = file_get_contents('php://input', false, null, 0, MAX_IMAGE_BYTES + 1);
if ($raw === false || $raw === '') {
    json_response(['error' => 'Prázdny obrázok – skontrolujte post_max_size na serveri'], 400);
}
if (strlen($raw) > MAX_IMAGE_BYTES) json_response(['error' => 'Obrázok je príliš veľký'], 413);
if (strncmp($raw, "\x89PNG\r\n\x1a\n", 8) !== 0) json_response(['error' => 'Súbor nie je PNG'], 415);

$info = @getimagesizefromstring($raw);
if (!$info || $info['mime'] !== 'image/png' || $info[0] > MAX_IMAGE_SIDE || $info[1] > MAX_IMAGE_SIDE) {
    json_response(['error' => 'Neplatný alebo príliš veľký PNG obrázok'], 415);
}

// metadáta
$m = [];
foreach (['d1name', 'd2name', 'startDate', 'competition', 'round', 'score1', 'score2', 'matchID'] as $k) {
    $m[$k] = mb_substr(trim((string)($_GET[$k] ?? '')), 0, 200, 'UTF-8');
}

try {
    $date = $m['startDate'] ? (new DateTime($m['startDate']))->setTimezone(new DateTimeZone('Europe/Bratislava')) : new DateTime('now', new DateTimeZone('Europe/Bratislava'));
} catch (Throwable $e) {
    $date = new DateTime('now', new DateTimeZone('Europe/Bratislava'));
}

$key  = $m['matchID'] !== '' ? $m['matchID'] : ($m['d1name'] . '|' . $m['d2name'] . '|' . $m['startDate']);
$name = $date->format('Y-m-d') . '_' . $type . '_' . slugify($m['d1name'], 30) . '-vs-' . slugify($m['d2name'], 30) . '_' . substr(sha1($type . '|' . $key . '|' . $client), 0, 8);
$dir  = STORAGE_DIR . $date->format('Y-m') . '/';

if (!ensure_storage_dir($dir)) {
    json_response(['error' => 'Priečinok storage/generated nie je zapisovateľný pre PHP'], 500);
}

$png = $dir . $name . '.png';
$jsonFile = $dir . $name . '.json';
$now = (new DateTime('now', new DateTimeZone('Europe/Bratislava')))->format('c');
$old = is_file($jsonFile) ? (json_decode((string)file_get_contents($jsonFile), true) ?: []) : [];

// atomický zápis (najprv dočasný súbor, potom premenovanie)
$tmp = $png . '.tmp' . bin2hex(random_bytes(3));
if (file_put_contents($tmp, $raw) === false || !rename($tmp, $png)) {
    @unlink($tmp);
    json_response(['error' => 'Obrázok sa nepodarilo zapísať'], 500);
}

$meta = $m + [
    'client'   => $client,     // hash anonymného ID prehliadača (kvôli galérii)
    'hidden'   => false,       // znovu vygenerovaný obrázok sa v galérii opäť zobrazí
    'type'     => $type,
    'typeName' => $types[$type],
    'width'    => $info[0],
    'height'   => $info[1],
    'created'  => $old['created'] ?? $now,
    'updated'  => $now,
];
file_put_contents($jsonFile, json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));

$rel = $date->format('Y-m') . '/' . $name . '.png';

// záznam do TXT logu (chyba logu nesmie zablokovať uloženie obrázka)
try {
    write_generation_log($meta + ['file' => $rel, 'bytes' => strlen($raw), 'replaced' => !empty($old)]);
} catch (Throwable $e) {
    error_log('match-day log: ' . $e->getMessage());
}
json_response([
    'ok'       => true,
    'file'     => $rel,
    'url'      => STORAGE_URL . $rel . '?v=' . filemtime($png),
    'absolute' => ROOT_URL . STORAGE_URL . $rel,
    'replaced' => !empty($old),
    'bytes'    => strlen($raw),
]);
