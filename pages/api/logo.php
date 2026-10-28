<?php
/**
 * Proxy pre logá družstiev.
 * Prehliadač nemôže kresliť obrázky z cudzej domény do canvasu a potom ho exportovať (CORS),
 * preto logo stiahneme tu, odstránime biele pozadie a vrátime PNG z našej domény.
 *
 *   GET api-logo?url=https://...
 */
$url = trim($_GET['url'] ?? '');
$cacheDir = md_cache_dir('logos');
// raw=1: fotka hráča – bez odstraňovania bieleho pozadia (to je iba pre logá)
$raw = !empty($_GET['raw']);
// s=32|64|128|256: zmenšený náhľad (WebP) na zobrazenie v zoznamoch – plné PNG iba pre canvas
$size = in_array((int)($_GET['s'] ?? 0), [32, 64, 128, 256, 512], true) ? (int)$_GET['s'] : 0;
$cacheFile = $cacheDir . '/' . sha1('v2|' . ($raw ? 'raw|' : '') . $url) . '.png';

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
    // nové logo sťahujeme zo Sportnetu – najviac 600 za hodinu z jednej IP adresy
    if (!md_rate_limit('logo|' . md_ip_hash(), 600, 3600)) { if ($fail(429, 'Príliš veľa požiadaviek') === 'stale') goto send; }
    // zámok: to isté logo sa pri súbežných požiadavkách spracuje iba raz
    $lock = @fopen($cacheFile . '.lock', 'c');
    if ($lock) flock($lock, LOCK_EX);
    clearstatcache(true, $cacheFile);
    if (!(is_file($cacheFile) && filesize($cacheFile) > 0 && filemtime($cacheFile) > time() - 7 * 86400)) {
        // iba povolené domény a verejné IP; presmerovania sa overujú (includes/security.php)
        $bin = md_safe_fetch_image($url);
        if (!$bin) { usleep(400000); $bin = md_safe_fetch_image($url); } // druhý pokus
        // ochrana pamäte: obrázok najviac 4000 × 4000 px
        $dim = $bin ? @getimagesizefromstring($bin) : false;
        if (!$dim || $dim[0] > 4000 || $dim[1] > 4000) $bin = false;
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
if ($size > 0 && function_exists('imagewebp')) {
    $small = substr($cacheFile, 0, -4) . '-' . $size . '.webp';
    if (!is_file($small) || filemtime($small) < filemtime($cacheFile)) {
        $src = @imagecreatefrompng($cacheFile);
        if ($src) {
            $w = imagesx($src); $h = imagesy($src);
            $r = min(1, $size / max($w, $h));
            $nw = max(1, (int)round($w * $r)); $nh = max(1, (int)round($h * $r));
            $dst = imagecreatetruecolor($nw, $nh);
            imagealphablending($dst, false); imagesavealpha($dst, true);
            imagefill($dst, 0, 0, imagecolorallocatealpha($dst, 0, 0, 0, 127));
            imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);
            $tmpS = $small . '.tmp' . getmypid();
            if (@imagewebp($dst, $tmpS, 92)) @rename($tmpS, $small); else @unlink($tmpS);
        }
    }
    if (is_file($small)) {
        header('Content-Type: image/webp');
        header('Cache-Control: public, max-age=2592000');
        header('Content-Length: ' . filesize($small));
        readfile($small);
        exit;
    }
}
header('Content-Type: image/png');
header('Cache-Control: public, max-age=604800');
header('Content-Length: ' . filesize($cacheFile));
readfile($cacheFile);
exit;
