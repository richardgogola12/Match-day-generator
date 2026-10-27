<?php
/**
 * Proxy pre logá družstiev.
 * Prehliadač nemôže kresliť obrázky z cudzej domény do canvasu a potom ho exportovať (CORS),
 * preto logo stiahneme tu, odstránime biele pozadie a vrátime PNG z našej domény.
 *
 *   GET api-logo?url=https://...
 */
$url = trim($_GET['url'] ?? '');
$cacheDir = sys_get_temp_dir() . '/match-day-logos';
// raw=1: fotka hráča – bez odstraňovania bieleho pozadia (to je iba pre logá)
$raw = !empty($_GET['raw']);
$cacheFile = $cacheDir . '/' . sha1(($raw ? 'raw|' : '') . $url) . '.png';

/** chyba: starú verziu z cache, inak presmerovanie na originál (fb=1, iba pre <img>) alebo 404 bez cache */
$fail = function ($code, $msg) use ($cacheFile, $url) {
    if (is_file($cacheFile) && filesize($cacheFile) > 0) return 'stale';
    header('Cache-Control: no-store');
    if (!empty($_GET['fb']) && preg_match('#^https?://#i', $url)) { header('Location: ' . $url, true, 302); exit; }
    http_response_code($code);
    exit($msg);
};

// platná cache sa vráti hneď – bez DNS dotazu a sťahovania (rýchle aj pri desiatkach lôg naraz)
$fresh = is_file($cacheFile) && filesize($cacheFile) > 0 && filemtime($cacheFile) > time() - 7 * 86400;
if (!$fresh) {
    if ($url === '' || !preg_match('#^https?://#i', $url)) { http_response_code(400); header('Cache-Control: no-store'); exit('Neplatná URL'); }
    // DNS občas zlyhá – skúsi sa dvakrát
    $ok = is_public_http_url($url);
    if (!$ok) { usleep(300000); $ok = is_public_http_url($url); }
    if (!$ok) { if ($fail(400, 'Neplatná URL') === 'stale') goto send; }
    if (!is_dir($cacheDir)) @mkdir($cacheDir, 0775, true);
    // zámok: to isté logo sa pri súbežných požiadavkách spracuje iba raz
    $lock = @fopen($cacheFile . '.lock', 'c');
    if ($lock) flock($lock, LOCK_EX);
    clearstatcache(true, $cacheFile);
    if (!(is_file($cacheFile) && filesize($cacheFile) > 0 && filemtime($cacheFile) > time() - 7 * 86400)) {
        $bin = fetch_binary($url);
        if (!$bin) { usleep(400000); $bin = fetch_binary($url); } // druhý pokus
        $img = $bin ? @imagecreatefromstring($bin) : false;
        if (!$img) {
            if ($lock) { flock($lock, LOCK_UN); fclose($lock); }
            if ($fail(404, 'Logo sa nepodarilo načítať') === 'stale') goto send;
        }
        imagepalettetotruecolor($img);
        imagesavealpha($img, true);
        $tmpOut = $cacheFile . '.tmp' . getmypid();
        if ($raw) {
            imagepng($img, $tmpOut);
        } else {
            // logo: odstráni biele pozadie (rovnaká logika ako imageFromUrl);
            // priehľadnosť sa musí zachovať, inak by sa priehľadné logo uložilo s čiernym pozadím
            imagealphablending($img, false);
            $tmp = tempnam(sys_get_temp_dir(), 'logo');
            imagepng($img, $tmp);
            $clean = imageFromUrl($tmp);
            @unlink($tmp);
            if ($clean) imagepng($clean, $tmpOut); else imagepng($img, $tmpOut);
            unset($clean);
        }
        unset($img);
        @rename($tmpOut, $cacheFile);
    }
    if ($lock) { flock($lock, LOCK_UN); fclose($lock); }
}

send:
while (ob_get_level() > 0) ob_end_clean();
header('Content-Type: image/png');
header('Cache-Control: public, max-age=604800');
readfile($cacheFile);
exit;
