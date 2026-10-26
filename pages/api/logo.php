<?php
/**
 * Proxy pre logá družstiev.
 * Prehliadač nemôže kresliť obrázky z cudzej domény do canvasu a potom ho exportovať (CORS),
 * preto logo stiahneme tu, odstránime biele pozadie a vrátime PNG z našej domény.
 *
 *   GET api-logo?url=https://...
 */
$url = trim($_GET['url'] ?? '');

if ($url === '' || !is_public_http_url($url)) {
    http_response_code(400);
    exit('Neplatná URL');
}

$cacheDir = sys_get_temp_dir() . '/match-day-logos';
// raw=1: fotka hráča – bez odstraňovania bieleho pozadia (to je iba pre logá)
$raw = !empty($_GET['raw']);
$cacheFile = $cacheDir . '/' . sha1(($raw ? 'raw|' : '') . $url) . '.png';

if (!is_file($cacheFile) || filemtime($cacheFile) < time() - 7 * 86400) {
    $bin = fetch_binary($url);
    $img = $bin ? @imagecreatefromstring($bin) : false;
    if (!$img) {
        http_response_code(404);
        exit('Logo sa nepodarilo načítať');
    }
    if ($raw) {
        if (!is_dir($cacheDir)) @mkdir($cacheDir, 0775, true);
        imagepalettetotruecolor($img);
        imagesavealpha($img, true);
        imagepng($img, $cacheFile);
        unset($img);
        goto send;
    }
    // logo: odstráni biele pozadie (rovnaká logika ako imageFromUrl);
    // priehľadnosť sa musí zachovať, inak by sa priehľadné logo uložilo s čiernym pozadím
    imagepalettetotruecolor($img);
    imagealphablending($img, false);
    imagesavealpha($img, true);
    $tmp = tempnam(sys_get_temp_dir(), 'logo');
    imagepng($img, $tmp);
    unset($img);
    $clean = imageFromUrl($tmp);
    @unlink($tmp);
    if (!$clean) {
        http_response_code(500);
        exit('Chyba spracovania loga');
    }
    if (!is_dir($cacheDir)) @mkdir($cacheDir, 0775, true);
    imagepng($clean, $cacheFile);
    unset($clean);
}

send:
while (ob_get_level() > 0) ob_end_clean();
header('Content-Type: image/png');
header('Cache-Control: public, max-age=604800');
readfile($cacheFile);
exit;
