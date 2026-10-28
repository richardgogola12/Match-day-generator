<?php
/**
 * Bezpečnosť, cache a údržba:
 *  - chránené priečinky storage/cache a storage/private,
 *  - HTTPS s overením certifikátu (záložný balík CA includes/cacert.pem),
 *  - bezpečné sťahovanie obrázkov (iba povolené domény, verejné IP, overené presmerovania),
 *  - obmedzenie počtu požiadaviek (anonymne – IP sa ukladá iba ako hash s tajným kľúčom),
 *  - bezpečnostné hlavičky, heslo správcu ako hash,
 *  - obnova cache na pozadí (stará verzia sa zobrazí hneď) a údržba úložiska.
 */

/* ------------------------------------------------------------------ */
/* Priečinky                                                           */
/* ------------------------------------------------------------------ */
function md_protect_dir($dir) {
    if (!is_dir($dir)) @mkdir($dir, 0775, true);
    if (!is_file($dir . '/.htaccess')) @file_put_contents($dir . '/.htaccess', "Require all denied\n<IfModule !mod_authz_core.c>\n    Order allow,deny\n    Deny from all\n</IfModule>\n");
    if (!is_file($dir . '/index.html')) @file_put_contents($dir . '/index.html', '');
    return is_dir($dir);
}

/** podpriečinok cache (storage/cache/…), z webu nedostupný */
function md_cache_dir($sub = '') {
    static $done = [];
    // ak storage/cache nie je zapisovateľný, použije sa dočasný priečinok systému (cache musí fungovať vždy)
    static $root = null;
    if ($root === null) { md_protect_dir(CACHE_DIR); $root = is_writable(CACHE_DIR) ? CACHE_DIR : sys_get_temp_dir() . '/match-day-cache-' . substr(sha1(APP_DIR), 0, 8); if (!is_dir($root)) @mkdir($root, 0775, true); }
    $dir = $root . ($sub !== '' ? '/' . $sub : '');
    if (!isset($done[$dir])) { if ($sub !== '' && !is_dir($dir)) @mkdir($dir, 0775, true); $done[$dir] = true; }
    return $dir;
}

function md_private_file($name) {
    static $ok = false;
    if (!$ok) { md_protect_dir(PRIVATE_DIR); $ok = true; }
    return PRIVATE_DIR . '/' . $name;
}

/** tajný kľúč aplikácie (vytvorí sa raz) – na hash IP adries */
function md_secret() {
    static $s = null;
    if ($s !== null) return $s;
    $f = md_private_file('secret.key');
    $s = is_file($f) ? trim((string)file_get_contents($f)) : '';
    if (strlen($s) < 32) { $s = bin2hex(random_bytes(32)); @file_put_contents($f, $s, LOCK_EX); }
    return $s;
}

function md_json_read($file, $default = []) {
    if (!is_file($file)) return $default;
    $d = json_decode((string)@file_get_contents($file), true);
    return is_array($d) ? $d : $default;
}

function md_json_write($file, array $data) {
    $tmp = $file . '.tmp' . getmypid();
    if (@file_put_contents($tmp, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), LOCK_EX) === false) return false;
    return @rename($tmp, $file);
}

/* ------------------------------------------------------------------ */
/* HTTPS                                                               */
/* ------------------------------------------------------------------ */
/** nastavenia cURL pre overenie certifikátu */
function md_curl_ssl_opts($fallbackBundle = false) {
    if (!SSL_VERIFY) return [CURLOPT_SSL_VERIFYPEER => false, CURLOPT_SSL_VERIFYHOST => 0];
    $o = [CURLOPT_SSL_VERIFYPEER => true, CURLOPT_SSL_VERIFYHOST => 2];
    if (SSL_CAINFO !== '' && is_file(SSL_CAINFO)) $o[CURLOPT_CAINFO] = SSL_CAINFO;
    elseif ($fallbackBundle && is_file(__DIR__ . '/cacert.pem')) $o[CURLOPT_CAINFO] = __DIR__ . '/cacert.pem';
    return $o;
}

/** chyba certifikátu (hosting bez aktuálnych CA) – skúsi sa záložný balík includes/cacert.pem */
function md_curl_ca_error($errno) {
    return in_array((int)$errno, [60, 77, 35, 51, 58, 83], true);
}

/* ------------------------------------------------------------------ */
/* Bezpečné sťahovanie obrázkov (logá, fotky)                          */
/* ------------------------------------------------------------------ */
/** domény z odpovedí Sportnetu, odkiaľ pochádzajú logá a fotky – povolia sa automaticky */
function md_learn_image_hosts($json) {
    if (!is_string($json) || $json === '') return;
    if (!preg_match_all('#"(?:logoUrl|logo_url|logo|public_url|publicUrl|photo|image|imageUrl|src|url)"\s*:\s*"https?:\\\\?/\\\\?/([a-z0-9.\-]+)#i', $json, $m)) return;
    $hosts = array_unique(array_map('strtolower', $m[1]));
    $f = md_cache_dir() . '/image-hosts.json';
    $known = md_json_read($f);
    $new = array_diff($hosts, array_keys($known));
    if (!$new) return;
    foreach ($new as $h) if (count($known) < 200) $known[$h] = date('c');
    md_json_write($f, $known);
}

function md_image_hosts() {
    $list = array_filter(array_map('trim', explode(',', strtolower(IMAGE_HOSTS))));
    $learned = array_keys(md_json_read(md_cache_dir() . '/image-hosts.json'));
    return array_values(array_unique(array_merge($list, $learned)));
}

function md_image_host_allowed($host) {
    $host = strtolower(rtrim((string)$host, '.'));
    if ($host === '') return false;
    foreach (md_image_hosts() as $h) if ($host === $h || str_ends_with($host, '.' . $h)) return true;
    return false;
}

/** zablokovaná doména – zapíše sa (správca ju uvidí v Kontrole Sportnetu) */
function md_log_blocked_host($host) {
    $f = md_cache_dir() . '/blocked-hosts.json';
    $d = md_json_read($f);
    $d[strtolower($host)] = ['n' => ($d[strtolower($host)]['n'] ?? 0) + 1, 'last' => date('c')];
    if (count($d) <= 100) md_json_write($f, $d);
}

/** overí URL a vráti [host, port, ip] alebo null (iba http/https, povolená doména, verejná IP) */
function md_check_image_url($url) {
    $p = parse_url($url);
    if (!$p || empty($p['host']) || !in_array(strtolower($p['scheme'] ?? ''), ['http', 'https'], true)) return null;
    if (isset($p['user']) || isset($p['pass'])) return null;
    $port = (int)($p['port'] ?? (strtolower($p['scheme']) === 'https' ? 443 : 80));
    if (!in_array($port, [80, 443], true) && !md_test_mode()) return null;
    if (!md_image_host_allowed($p['host'])) { md_log_blocked_host($p['host']); return null; }
    $ips = @gethostbynamel($p['host']);
    if (!$ips) { usleep(250000); $ips = @gethostbynamel($p['host']); }
    if (!$ips) return null;
    foreach ($ips as $ip) {
        if (!md_test_mode() && !filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) return null;
    }
    return [$p['host'], $port, $ips[0]];
}

/** testovacia kópia s vymyslenými dátami (127.0.0.1) – iba ak je výslovne zapnutá */
function md_test_mode() { return defined('MD_TEST_MODE') && MD_TEST_MODE; }

/**
 * Stiahne obrázok: presmerovania sa sledujú ručne a každé sa znova overí,
 * spojenie ide presne na overenú IP adresu (žiadne podvrhnutie cez DNS).
 */
function md_safe_fetch_image($url, $maxBytes = 5242880) {
    for ($hop = 0; $hop < 4; $hop++) {
        $chk = md_check_image_url($url);
        if (!$chk) return false;
        [$host, $port, $ip] = $chk;
        foreach ([false, true] as $fallback) {
            $ch = curl_init();
            $opts = [
                CURLOPT_URL => $url, CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => false, CURLOPT_HEADER => true,
                CURLOPT_TIMEOUT => 15, CURLOPT_CONNECTTIMEOUT => 6,
                CURLOPT_RESOLVE => [$host . ':' . $port . ':' . $ip],
                CURLOPT_USERAGENT => 'Mozilla/5.0 (compatible; MatchDayGenerator/1.0)',
                CURLOPT_NOPROGRESS => false,
                CURLOPT_PROGRESSFUNCTION => fn($c, $dlTotal, $dl) => $dl > $maxBytes ? 1 : 0,
            ] + md_curl_ssl_opts($fallback);
            if (defined('CURLOPT_PROTOCOLS_STR')) $opts[CURLOPT_PROTOCOLS_STR] = 'http,https';
            curl_setopt_array($ch, $opts);
            $resp = curl_exec($ch);
            $errno = curl_errno($ch);
            if ($resp === false && !$fallback && md_curl_ca_error($errno)) continue;
            break;
        }
        if ($resp === false) return false;
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $hsize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $head = substr($resp, 0, $hsize);
        $body = substr($resp, $hsize);
        if ($code >= 300 && $code < 400 && preg_match('/^Location:\s*(\S+)/mi', $head, $m)) {
            $loc = trim($m[1]);
            if (!preg_match('#^https?://#i', $loc)) { // relatívne presmerovanie
                $p = parse_url($url);
                $loc = $p['scheme'] . '://' . $p['host'] . (isset($p['port']) ? ':' . $p['port'] : '') . ($loc[0] === '/' ? $loc : '/' . $loc);
            }
            $url = $loc;
            continue;
        }
        if ($code !== 200 || $body === '' || strlen($body) > $maxBytes) return false;
        return $body;
    }
    return false;
}

/* ------------------------------------------------------------------ */
/* Obmedzenie počtu požiadaviek                                        */
/* ------------------------------------------------------------------ */
/** anonymný identifikátor IP adresy (IP sa nikam neukladá) */
function md_ip_hash() {
    return hash_hmac('sha256', (string)($_SERVER['REMOTE_ADDR'] ?? ''), md_secret());
}

/**
 * Posuvné okno: true = požiadavka je povolená (a započíta sa), false = limit prekročený.
 */
function md_rate_limit($bucket, $max, $window) {
    if ($max <= 0) return true;
    $f = md_cache_dir('ratelimit') . '/' . sha1($bucket) . '.json';
    $fh = @fopen($f, 'c+');
    if (!$fh) return true;
    flock($fh, LOCK_EX);
    $hits = json_decode((string)stream_get_contents($fh), true) ?: [];
    $now = time();
    $hits = array_values(array_filter($hits, fn($t) => $t > $now - $window));
    $ok = count($hits) < $max;
    if ($ok) $hits[] = $now;
    ftruncate($fh, 0); rewind($fh);
    fwrite($fh, json_encode($hits));
    flock($fh, LOCK_UN); fclose($fh);
    return $ok;
}

/** zostávajúce sekundy do uvoľnenia limitu */
function md_rate_retry_after($bucket, $window) {
    $hits = md_json_read(md_cache_dir('ratelimit') . '/' . sha1($bucket) . '.json');
    return $hits ? max(1, min($hits) + $window - time()) : 0;
}

/* ------------------------------------------------------------------ */
/* Bezpečnostné hlavičky                                               */
/* ------------------------------------------------------------------ */
function md_security_headers() {
    if (headers_sent()) return;
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=(), usb=()');
    header('Cross-Origin-Opener-Policy: same-origin');
    // Content-Security-Policy: skripty a štýly iba z nášho servera (Google Fonts len pre voliteľné písma v šablónach)
    header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; "
        . "font-src 'self' data: blob: https://fonts.gstatic.com; img-src 'self' data: blob: https: http:; connect-src 'self'; "
        . "worker-src 'self'; manifest-src 'self'; frame-ancestors 'self'; base-uri 'self'; form-action 'self'; object-src 'none'");
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') header('Strict-Transport-Security: max-age=15552000');
}

/* ------------------------------------------------------------------ */
/* Heslo správcu (iba hash)                                            */
/* ------------------------------------------------------------------ */
/**
 * Hash hesla správcu. Poradie: heslo zmenené v aplikácii (storage/private/admin.json),
 * inak GALLERY_ADMIN_KEY z konfigurácie (hash alebo – staršie – čisté heslo, z ktorého sa hash vytvorí).
 * Ak sa GALLERY_ADMIN_KEY v konfigurácii zmení, platí znova heslo z konfigurácie.
 */
function md_admin_hash_reload() { $GLOBALS['md_admin_hash_cache'] = null; return md_admin_hash(); }

function md_admin_hash() {
    $h = $GLOBALS['md_admin_hash_cache'] ?? null;
    if ($h !== null) return $h;
    $h = md_admin_hash_compute();
    return $GLOBALS['md_admin_hash_cache'] = $h;
}

function md_admin_hash_compute() {
    $h = null;
    if (GALLERY_ADMIN_KEY === '') return $h = '';
    $f = md_private_file('admin.json');
    $d = md_json_read($f);
    $from = hash('sha256', 'cfg|' . GALLERY_ADMIN_KEY);
    if (!empty($d['hash']) && ($d['from'] ?? '') === $from) return $h = $d['hash'];
    $isHash = (bool)preg_match('/^\$(2y|2a|argon2i|argon2id)\$/', GALLERY_ADMIN_KEY);
    if ($isHash) return GALLERY_ADMIN_KEY;
    $h = password_hash(GALLERY_ADMIN_KEY, PASSWORD_DEFAULT);
    if (md_json_write($f, ['hash' => $h, 'from' => $from, 'changed' => false, 'at' => date('c')])) return $h;
    // storage/private nie je zapisovateľný – overenie bez uloženého hashu (stále bez čistého hesla v session)
    return 'cfg:' . hash('sha256', 'md-pw|' . GALLERY_ADMIN_KEY);
}

function md_admin_verify($password) {
    $h = md_admin_hash();
    if ($h === '' || !is_string($password) || $password === '') return false;
    if (str_starts_with($h, 'cfg:')) return hash_equals($h, 'cfg:' . hash('sha256', 'md-pw|' . $password));
    return password_verify($password, $h);
}

/** zmena hesla v aplikácii – uloží sa iba hash */
function md_admin_set_password($new) {
    $from = hash('sha256', 'cfg|' . GALLERY_ADMIN_KEY);
    return md_json_write(md_private_file('admin.json'), ['hash' => password_hash($new, PASSWORD_DEFAULT), 'from' => $from, 'changed' => true, 'at' => date('c')]);
}

function md_admin_is_default() {
    return md_admin_verify('asd123');
}

/* ------------------------------------------------------------------ */
/* Obnova cache na pozadí                                              */
/* ------------------------------------------------------------------ */
$GLOBALS['md_bg_tasks'] = [];
$GLOBALS['md_in_bg'] = false;

/** úloha, ktorá sa spustí až po odoslaní stránky návštevníkovi */
function md_background($key, callable $fn) {
    if (isset($GLOBALS['md_bg_tasks'][$key])) return;
    if (!$GLOBALS['md_bg_tasks']) register_shutdown_function('md_run_background');
    $GLOBALS['md_bg_tasks'][$key] = $fn;
}

function md_run_background() {
    $tasks = $GLOBALS['md_bg_tasks'];
    if (!$tasks) return;
    ignore_user_abort(true);
    // odošle stránku a zatvorí spojenie (PHP-FPM / LiteSpeed); inak aspoň vyprázdni výstup
    if (function_exists('fastcgi_finish_request')) fastcgi_finish_request();
    elseif (function_exists('litespeed_finish_request')) litespeed_finish_request();
    else { while (ob_get_level() > 0) @ob_end_flush(); @flush(); }
    @set_time_limit(120);
    $GLOBALS['md_in_bg'] = true;
    foreach ($tasks as $fn) { try { $fn(); } catch (Throwable $e) { error_log('match-day bg: ' . $e->getMessage()); } }
}

/**
 * Stale-while-revalidate: ak je cache po platnosti, vráti ju hneď a obnovu naplánuje na pozadie.
 * Vracia null, ak treba počítať hneď (cache nie je, je čerstvá, alebo už bežíme na pozadí).
 */
function md_bg_stale($cacheF, $ttl, callable $refresh, $maxStale = 7 * 86400) {
    if ($GLOBALS['md_in_bg'] || !is_file($cacheF)) return null;
    $age = time() - filemtime($cacheF);
    if ($age <= $ttl || $age > $maxStale) return null;
    $d = json_decode((string)@file_get_contents($cacheF), true);
    if (!is_array($d)) return null;
    // iba jedna obnova naraz (zámok platí 2 minúty)
    $lock = $cacheF . '.refresh';
    if (!is_file($lock) || filemtime($lock) < time() - 120) {
        @touch($lock);
        md_background($cacheF, function () use ($refresh, $lock) { $refresh(); @unlink($lock); });
    }
    return $d;
}

/* ------------------------------------------------------------------ */
/* Úložisko obrázkov a údržba                                          */
/* ------------------------------------------------------------------ */
/** obsadené miesto obrázkami (bajty), počíta sa najviac raz za 10 minút */
function md_storage_usage($force = false) {
    $f = md_cache_dir() . '/storage-usage.json';
    $d = md_json_read($f);
    if (!$force && isset($d['bytes']) && ($d['t'] ?? 0) > time() - 600) return (int)$d['bytes'];
    $bytes = 0; $n = 0;
    foreach (glob(STORAGE_DIR . '*/*') ?: [] as $file) if (is_file($file)) { $bytes += filesize($file); $n++; }
    md_json_write($f, ['bytes' => $bytes, 'files' => $n, 't' => time()]);
    return $bytes;
}

function md_storage_add($bytes) {
    $f = md_cache_dir() . '/storage-usage.json';
    $d = md_json_read($f);
    if (isset($d['bytes'])) { $d['bytes'] += $bytes; md_json_write($f, $d); }
}

function md_storage_full($incoming = 0) {
    return STORAGE_MAX_MB > 0 && md_storage_usage() + $incoming > STORAGE_MAX_MB * 1048576;
}

/**
 * Údržba (najviac raz za deň, alebo hneď s $force):
 * staré obrázky (STORAGE_KEEP_DAYS), staré súbory cache, záznamy limitov.
 * @return array čo sa zmazalo
 */
function md_maintenance($force = false) {
    $stamp = md_private_file('maintenance.json');
    $st = md_json_read($stamp);
    if (!$force && ($st['t'] ?? 0) > time() - 86400) return ['skipped' => true];
    md_json_write($stamp, ['t' => time()] + $st);
    $out = ['images' => 0, 'bytes' => 0, 'cache' => 0];
    if (STORAGE_KEEP_DAYS > 0) {
        $limit = time() - STORAGE_KEEP_DAYS * 86400;
        foreach (glob(STORAGE_DIR . '*/*.json') ?: [] as $jf) {
            $meta = md_json_read($jf);
            $t = strtotime($meta['updated'] ?? '') ?: filemtime($jf);
            if ($t >= $limit) continue;
            $base = substr($jf, 0, -5);
            foreach ([$base . '.png', $base . '.thumb.jpg', $jf] as $x) if (is_file($x)) { $out['bytes'] += filesize($x); @unlink($x); }
            $out['images']++;
        }
        foreach (glob(STORAGE_DIR . '*', GLOB_ONLYDIR) ?: [] as $dir) if (!(glob($dir . '/*') ?: [])) @rmdir($dir);
    }
    // cache Sportnetu staršia ako 30 dní, logá staršie ako 60 dní, limity staršie ako 2 dni
    foreach ([[md_cache_dir('sportnet'), 30], [md_cache_dir('logos'), 60], [md_cache_dir('ratelimit'), 2]] as [$dir, $days]) {
        foreach (glob($dir . '/*') ?: [] as $file) if (is_file($file) && filemtime($file) < time() - $days * 86400) { @unlink($file); $out['cache']++; }
    }
    md_storage_usage(true);
    md_json_write($stamp, ['t' => time(), 'last' => $out + ['at' => date('c')]]);
    return $out;
}

/* ------------------------------------------------------------------ */
/* Dostupnosť Sportnetu                                                */
/* ------------------------------------------------------------------ */
/** zaznamená úspech / chybu spojenia so Sportnetom (na upozornenie pri výpadku) */
function md_sportnet_status($ok) {
    $f = md_cache_dir() . '/sportnet-status.json';
    $d = md_json_read($f, ['fail' => 0, 'ok' => 0]);
    if ($ok) { if ($d['fail'] === 0 && ($d['ok'] ?? 0) > time() - 60) return; $d = ['fail' => 0, 'ok' => time(), 'since' => 0]; }
    else { $d['fail']++; $d['since'] = $d['since'] ?: time(); $d['last'] = time(); }
    md_json_write($f, $d);
}

/** Sportnet práve nefunguje? (aspoň 3 chyby za sebou za posledných 10 minút) */
function md_sportnet_down() {
    static $r = null;
    if ($r !== null) return $r;
    $d = md_json_read(md_cache_dir() . '/sportnet-status.json', ['fail' => 0]);
    return $r = ($d['fail'] ?? 0) >= 3 && ($d['last'] ?? 0) > time() - 600;
}

/* ------------------------------------------------------------------ */
/* Logá na stránkach: zmenšený náhľad (WebP) cez server                */
/* ------------------------------------------------------------------ */
/** adresa zmenšeného loga (64 px stačí pre zoznamy, 128 pre hlavičky); pri chybe server presmeruje na originál */
function md_logo($url, $size = 64) {
    $url = (string)$url;
    if ($url === '') return '';
    return url('api-logo') . '?' . http_build_query(['s' => $size, 'fb' => 1, 'url' => $url]);
}

require_once __DIR__ . '/mail.php';
