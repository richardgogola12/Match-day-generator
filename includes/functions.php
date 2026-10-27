<?php



/**
 * Define class autoload
 */
spl_autoload_register(function ($className) {
    $file = CLASSES_DIR . $className . '.php';

    if (file_exists($file)) {
        require_once $file;
    }
});

/**
 * Dump
 */
function d() {
    foreach (func_get_args() as $arg) var_dump($arg);
}

/**
 * Dump and die
 */
function dd() {
    foreach (func_get_args() as $arg) d($arg);
    exit;
}

/**
 * Build absolute URL address
 */
function url($url = "/") {
    return BASE_PATH . "/" . ltrim($url, "/");
}

/**
 * URL to a static asset with cache-busting version
 */
function asset($path) {
    $path = ltrim($path, "/");
    $file = ASSETS_DIR . $path;
    $v = file_exists($file) ? filemtime($file) : 0;
    return url("assets/" . $path) . "?v=" . $v;
}

/**
 * Current route name (used for active nav item)
 */
function current_page() {
    return segment(1) ?: "";
}

/**
 * Redirect to another page
 */
function redirect($page = "/", $status_code = 301) {
    $page = str_replace(APP_URL, '', $page);
    $page = ltrim($page, '/');
    $location = APP_URL . "/" . $page;

    $codes = [
        301 => "Moved Permanently",
        302 => "Found",
        303 => "See Other",
        307 => "Temporary Redirect",
    ];

    if (!isset($codes[$status_code])) $status_code = 301;

    header("Location: " . $location, true, $status_code);
    header("Connection: close");
    exit;
}

/**
 * Sanitize string
 */
function plain($string) {
    return htmlspecialchars((string)$string, ENT_QUOTES, 'UTF-8');
}

/**
 * Sanitize array
 */
function heal_output($x = null) {
    if (!isset($x)) {
        return null;
    } elseif (is_string($x)) {
        return plain($x);
    } elseif (is_array($x)) {
        foreach ($x as $k => $v) {
            $x[$k] = heal_output($v);
        }
        return $x;
    }
    return $x;
}

/**
 * Check for post request
 */
function is_post_request() {
    return (
        isset($_SERVER["REQUEST_METHOD"]) &&
        strtolower($_SERVER["REQUEST_METHOD"]) === "post"
    );
}

/**
 * Check for ajax request
 */
function is_ajax_request() {
    return (
        isset($_SERVER['HTTP_X_REQUESTED_WITH']) &&
        strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest'
    );
}

/**
 * Include page content
 */
function show_page($view) {
    if (!file_exists($file = PAGES_DIR . $view . ".php")) {
        exit("Page file {$file} not found.");
    }
    include $file;
}

/**
 * Display error page 401
 */
function show_401() {
    header("HTTP/1.0 401 Unauthorized");
    if (!file_exists($file = APP_DIR . "/_app/pages/error/error-403.php")) {
        exit("Page file {$file} not found (and page also not found).");
    }
    include $file;
    exit;
}

/**
 * Display error page 403
 */
function show_403() {
    header("HTTP/1.0 403 Forbidden");
    if (!file_exists($file = APP_DIR . "/_app/pages/error/error-403.php")) {
        exit("Page file {$file} not found (and page also not found).");
    }
    include $file;
    exit;
}

/**
 * Display error page 404
 */
function show_404() {
    header("HTTP/1.0 404 Not Found");
    include PAGES_DIR . "404.php";
    exit;
}

/**
 * Include page layout
 */
function add_layout($layout, $data = []) {
    if (!file_exists($file = LAYOUT_DIR . $layout . ".php")) {
        exit("Layout file {$file} not found.");
    }
    extract(heal_output($data));
    extract($data, EXTR_PREFIX_ALL, "");
    include $file;
}

/**
 * Return URL segments (relative to BASE_PATH)
 */
function get_segments() {
    static $segments = null;
    if ($segments !== null) return $segments;

    $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
    $path = rawurldecode($path);
    if (BASE_PATH !== '' && str_starts_with($path, BASE_PATH)) {
        $path = substr($path, strlen(BASE_PATH));
    }
    $path = trim($path, '/');
    if (str_ends_with($path, 'index.php')) $path = trim(substr($path, 0, -9), '/');

    return $segments = explode('/', $path);
}

/**
 * Return segment by index
 */
function segment($index) {
    $segments = get_segments();
    return $segments[$index - 1] ?? "";
}

/**
 * Add new user message into session
 */
function add_message($msg_str, $msg_type = MSG_INFO) {
    if (!has_messages()) {
        $_SESSION["messages"] = [];
    }
    $_SESSION["messages"][] = [$msg_type, $msg_str];
}

/**
 * Add new error message
 */
function add_message_error($message) {
    add_message('Chyba aplikácie: ' . $message, MSG_ERROR);
}

/**
 * Get messages
 */
function get_messages() {
    if (!has_messages()) return [[]];

    $messages = $_SESSION["messages"];
    unset($_SESSION["messages"]);
    return $messages;
}

/**
 * Check if messages exist
 */
function has_messages() {
    return isset($_SESSION["messages"]) && is_array($_SESSION["messages"]);
}

/**
 * Convert first letter to lowercase
 */
function first_to_lc($str) {
    $str[0] = strtolower($str[0]);
    return $str;
}

function mesiace(){
    $mesiace = [
        1 => 'Jan', 2 => 'Feb', 3 => 'Mar', 4 => 'Apr',
        5 => 'Máj', 6 => 'Jún', 7 => 'Júl', 8 => 'Aug',
        9 => 'Sep', 10 => 'Okt', 11 => 'Nov', 12 => 'Dec'
    ];
    return $mesiace;
}

function getJsonFromUrl(string $url, bool $assoc = true) {

    $ch = curl_init();
    if (!$ch) {
        throw new Exception('Unable to initialize cURL');
    }

    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT => 20,

        // simulácia prehliadača (kľúčové pre hosting)
        CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120 Safari/537.36',


        // HTTP nastavenia
        CURLOPT_HTTPHEADER => [
            'Accept: application/json',
            'Accept-Language: sk-SK,sk;q=0.9,en;q=0.8',
            'Cache-Control: no-cache',
            'Pragma: no-cache'
        ],

        // stabilita medzi server-mi
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,

        // SSL (zachováš, kým nebude CA cert)
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr  = curl_error($ch);

    // curl handle sa uvoľní automaticky (curl_close je od PHP 8.5 zastarané)

    // cURL chyba
    if ($response === false) {
        throw new Exception('cURL error: ' . $curlErr);
    }

    // HTTP chyba – ALE NEZHODÍME APLIKÁCIU
    if ($httpCode !== 200) {
        return [
            'error' => true,
            'http_code' => $httpCode,
            'url' => $url,
            'response' => $response
        ];
    }

    $data = json_decode($response, $assoc);

    if (json_last_error() !== JSON_ERROR_NONE) {
        return [
            'error' => true,
            'http_code' => 200,
            'json_error' => json_last_error_msg(),
            'raw' => $response
        ];
    }

    return $data;
}


/**
 * Skontroluje, či sa hľadaný reťazec nachádza v texte
 *
 * @param string $text - text, v ktorom hľadáme
 * @param string $hladany - hľadaný reťazec
 * @return bool - true ak sa nachádza, false ak nie
 */
function obsahuje($text, $hladany) {
    // Pre PHP 8+
    if (str_contains($text, $hladany)) {
        return true;
    }
    return false;

    // Pre staršie verzie PHP môžeš použiť:
    // return strpos($text, $hladany) !== false;
}

// function imageFromUrl(string $url) {
//     $data = @file_get_contents($url);
//     if ($data === false) {
//         return false;
//     }

//     return @imagecreatefromstring($data);
// }

function imageFromUrl($url) {

    $data = @file_get_contents($url);
    if ($data === false) return false;

    $img = @imagecreatefromstring($data);
    if (!$img) return false;

    imagepalettetotruecolor($img);
    imagealphablending($img, false);
    imagesavealpha($img, true);

    $w = imagesx($img);
    $h = imagesy($img);

    // zmenšiť veľké obrázky (ochrana RAM)
    $max = 800;

    if ($w > $max || $h > $max) {

        $ratio = min($max/$w, $max/$h);
        $nw = (int)($w * $ratio);
        $nh = (int)($h * $ratio);

        $tmp = imagecreatetruecolor($nw, $nh);
        imagealphablending($tmp, false);
        imagesavealpha($tmp, true);

        imagecopyresampled($tmp, $img, 0,0,0,0, $nw,$nh, $w,$h);

        unset($img);
        $img = $tmp;

        $w = $nw;
        $h = $nh;
    }

    $transparent = imagecolorallocatealpha($img, 0,0,0,127);

    $limit = 240;

    $isWhite = function($rgb) use ($limit) {
        $r = ($rgb >> 16) & 0xFF;
        $g = ($rgb >> 8) & 0xFF;
        $b = $rgb & 0xFF;
        return ($r > $limit && $g > $limit && $b > $limit);
    };

    $stack = [];

    for ($x = 0; $x < $w; $x++) {
        $stack[] = [$x,0];
        $stack[] = [$x,$h-1];
    }

    for ($y = 0; $y < $h; $y++) {
        $stack[] = [0,$y];
        $stack[] = [$w-1,$y];
    }

    while ($stack) {

        [$x,$y] = array_pop($stack);

        if ($x < 0 || $y < 0 || $x >= $w || $y >= $h) continue;

        $rgb = imagecolorat($img,$x,$y);

        if (!$isWhite($rgb)) continue;

        imagesetpixel($img,$x,$y,$transparent);

        $stack[] = [$x+1,$y];
        $stack[] = [$x-1,$y];
        $stack[] = [$x,$y+1];
        $stack[] = [$x,$y-1];
    }

    return $img;
}


function wordWrapTTFCenter($text, $fontFile, $fontSize, $maxWidth, $centerX) {
    $words = explode(' ', $text);
    $lines = [];
    $currentLine = '';

    foreach ($words as $word) {
        $testLine = $currentLine === '' ? $word : $currentLine . ' ' . $word;
        $bbox = imagettfbbox($fontSize, 0, $fontFile, $testLine);
        $lineWidth = $bbox[2] - $bbox[0];

        if ($lineWidth > $maxWidth && $currentLine !== '') {
            $lines[] = $currentLine;
            $currentLine = $word;
        } else {
            $currentLine = $testLine;
        }
    }

    if ($currentLine !== '') {
        $lines[] = $currentLine;
    }

    return array_map(function($line) use ($fontFile, $fontSize, $centerX) {
        $bbox = imagettfbbox($fontSize, 0, $fontFile, $line);
        $lineWidth = $bbox[2] - $bbox[0];
        $x = $centerX - ($lineWidth / 2);
        return ['text' => $line, 'x' => round($x)];
    }, $lines);
}

/**
 * Z web stránky vytiahne text, ktorý obsahuje self.__next_f.push([1,"c:["$","$L25",null,{"teams"
 *
 * @param string $url       URL stránky
 * @return string|false     Obsah alebo false
 */
function getTeamsContent(string $url)
{
    $needle = 'self.__next_f.push([1,"c:[\"$\",\"$L25\",null,{\"teams\"';

    // Stiahni HTML
    $context = stream_context_create([
        'http' => [
            'header' => "User-Agent: Mozilla/5.0\r\n"
        ]
    ]);

    $html = @file_get_contents($url, false, $context);
    if ($html === false) {
        return false;
    }

    // Načítaj HTML do DOM
    libxml_use_internal_errors(true);
    $dom = new DOMDocument();
    $dom->loadHTML($html);
    libxml_clear_errors();

    // Prejdi všetky <script> tagy
    $scripts = $dom->getElementsByTagName('script');

    foreach ($scripts as $script) {
        $content = $script->textContent;

        if (strpos($content, $needle) !== false) {
            return trim($content); // vraciame len obsah, nie <script>
        }
    }

    return false;
}

// =================================================================================
// Pomocné funkcie pre nový dizajn a API pre prehliadač
// =================================================================================

/**
 * JSON odpoveď a koniec
 */
function json_response($data, $status = 200) {
    // zahodí čokoľvek, čo sa vypísalo pred tým (varovania PHP a pod.)
    while (ob_get_level() > 0) ob_end_clean();
    if (APP_DEBUG && $status >= 400 && !empty($GLOBALS['php_warnings'])) {
        $data['php_warnings'] = array_slice($GLOBALS['php_warnings'], 0, 5);
    }
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/**
 * Overí, že URL je verejná http(s) adresa (ochrana proxy pred SSRF)
 */
function is_public_http_url($url) {
    $parts = parse_url($url);
    if (!$parts || empty($parts['host'])) return false;
    if (!in_array(strtolower($parts['scheme'] ?? ''), ['http', 'https'], true)) return false;

    $ips = @gethostbynamel($parts['host']);
    if (!$ips) return false;
    foreach ($ips as $ip) {
        if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            return false;
        }
    }
    return true;
}

/**
 * Stiahne binárny obsah z URL (napr. logo); vracia string alebo false
 */
function fetch_binary($url, $maxBytes = 5242880) {
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS      => 3,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_USERAGENT      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120 Safari/537.36',
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
    ]);
    if (defined('CURLOPT_PROTOCOLS_STR')) {
        curl_setopt($ch, CURLOPT_PROTOCOLS_STR, 'http,https');
        curl_setopt($ch, CURLOPT_REDIR_PROTOCOLS_STR, 'http,https');
    }
    $data = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    // curl handle sa uvoľní automaticky (curl_close je od PHP 8.5 zastarané)

    if ($data === false || $code !== 200 || strlen($data) > $maxBytes) return false;
    return $data;
}

/**
 * Ľudsky čitateľný stav zápasu + farba badge
 */
function match_state_badge($state) {
    switch ($state) {
        case 'ODOHRATY':                    return ['Odohraté', 'success'];
        case 'NEZUCASTNENE_JEDNO_DRUZSTVO': return ['Voľno', 'secondary'];
        case '':
        case null:                          return ['Plánované', 'info'];
        default:
            $txt = mb_strtolower(str_replace('_', ' ', $state), 'UTF-8');
            return [mb_strtoupper(mb_substr($txt, 0, 1)) . mb_substr($txt, 1), 'warning'];
    }
}

/**
 * Formát dátumu "21. Okt 2025" a čas "18:00" (Europe/Bratislava)
 */
function format_match_date($iso) {
    if (!$iso) return ['', ''];
    $date = new DateTime($iso);
    $date->setTimezone(new DateTimeZone('Europe/Bratislava'));
    $m = mesiace()[(int)$date->format('n')];
    return [$date->format('j') . '. ' . $m . ' ' . $date->format('Y'), $date->format('H:i')];
}


// =================================================================================
// Galéria uložených obrázkov
// =================================================================================

/**
 * Bezpečný "slug" pre názov súboru (odstráni diakritiku)
 */
function slugify($text, $max = 40) {
    $map = ['á'=>'a','ä'=>'a','č'=>'c','ď'=>'d','é'=>'e','ě'=>'e','í'=>'i','ĺ'=>'l','ľ'=>'l','ň'=>'n','ó'=>'o','ô'=>'o','ö'=>'o','ő'=>'o',
            'ŕ'=>'r','ř'=>'r','š'=>'s','ť'=>'t','ú'=>'u','ů'=>'u','ü'=>'u','ű'=>'u','ý'=>'y','ž'=>'z','ß'=>'ss'];
    $text = mb_strtolower((string)$text, 'UTF-8');
    $text = strtr($text, $map);
    if (function_exists('iconv')) {
        $t = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
        if ($t !== false) $text = $t;
    }
    $text = trim(preg_replace('/[^a-z0-9]+/', '-', $text), '-');
    return substr($text, 0, $max) ?: 'x';
}

/** Názvy typov obrázkov */
function image_types() {
    return ['matchday' => 'Match day', 'lineup' => 'Zostava', 'result' => 'Výsledok', 'goals' => 'Strelci', 'report' => 'Súhrn zápasu',
            'h2h' => 'Vzájomné zápasy', 'form' => 'Forma', 'weekend' => 'Víkend klubu',
            'squad' => 'Súpiska', 'table' => 'Tabuľka', 'round' => 'Kolo', 'stats' => 'Štatistiky', 'month' => 'Hráč mesiaca',
            'player' => 'Profil hráča', 'motm' => 'Hráč zápasu', 'milestone' => 'Jubileum', 'birthday' => 'Narodeniny'];
}

/**
 * Pripraví priečinok na obrázky (vrátane ochrany pred spúšťaním skriptov)
 */
function ensure_storage_dir($dir) {
    if (!is_dir($dir) && !@mkdir($dir, 0775, true)) return false;
    $root = rtrim(STORAGE_DIR, '/');
    if (!is_file($root . '/.htaccess')) {
        @file_put_contents($root . '/.htaccess',
            "Options -Indexes\n<FilesMatch \"\\.(php|phtml|phar|pl|py|cgi|sh)$\">\n    Require all denied\n</FilesMatch>\n");
    }
    if (!is_file($root . '/index.html')) @file_put_contents($root . '/index.html', '');
    return is_writable($dir);
}

/**
 * Anonymné ID prehliadača z hlavičky X-MD-Client -> hash (na disk sa neukladá pôvodné ID)
 */
function client_hash() {
    $id = strtolower(trim($_SERVER['HTTP_X_MD_CLIENT'] ?? ''));
    return preg_match('/^[a-f0-9]{32}$/', $id) ? hash('sha256', 'match-day|' . $id) : '';
}


/**
 * Zoznam uložených obrázkov (najnovšie prvé)
 * @param string|null $client hash prehliadača; null = všetky (správca)
 * @param string      $viewer hash prehliadača, ktorý sa pozerá (kvôli príznaku "owner")
 */
function gallery_items($client = null, $viewer = '') {
    $items = [];
    foreach (glob(STORAGE_DIR . '*/*.json') ?: [] as $jsonFile) {
        $meta = json_decode((string)@file_get_contents($jsonFile), true);
        $png = substr($jsonFile, 0, -5) . '.png';
        if (!is_array($meta) || !is_file($png)) continue;
        if ($client !== null && (($meta['client'] ?? '') !== $client || !empty($meta['hidden']))) continue;
        $rel = basename(dirname($png)) . '/' . basename($png);
        $v = filemtime($png);
        $meta['file'] = $rel;
        $meta['url'] = STORAGE_URL . $rel . '?v=' . $v;
        $thumb = gallery_thumb($png);
        $meta['thumb'] = $thumb ? STORAGE_URL . basename(dirname($png)) . '/' . basename($thumb) . '?v=' . $v : $meta['url'];
        $meta['bytes'] = filesize($png);
        $meta['owner'] = $viewer !== '' && ($meta['client'] ?? '') === $viewer;
        $meta['hidden'] = !empty($meta['hidden']);
        unset($meta['client'], $meta['hiddenAt']);
        $items[] = $meta;
    }
    usort($items, fn($a, $b) => strcmp($b['updated'] ?? '', $a['updated'] ?? ''));
    return $items;
}

/**
 * Overí relatívnu cestu k obrázku v galérii; vráti absolútnu cestu alebo false
 */
function gallery_path($rel) {
    if (!preg_match('#^\d{4}-\d{2}/[a-z0-9_\-]+\.png$#', (string)$rel)) return false;
    $path = STORAGE_DIR . $rel;
    $real = realpath($path);
    $root = realpath(STORAGE_DIR);
    if (!$real || !$root || !str_starts_with($real, $root . DIRECTORY_SEPARATOR)) return false;
    return $real;
}

/**
 * Zmenšený náhľad do galérie (JPEG, max. 480 px) – vytvorí sa raz a uloží vedľa PNG.
 * Galéria tak nesťahuje plné PNG (často niekoľko MB). Vracia cestu k náhľadu alebo false.
 */
function gallery_thumb($png, $max = 480) {
    $thumb = substr($png, 0, -4) . '.thumb.jpg';
    if (is_file($thumb) && filemtime($thumb) >= filemtime($png)) return $thumb;
    if (!function_exists('imagecreatefrompng')) return false;

    $src = @imagecreatefrompng($png);
    if (!$src) return false;
    $w = imagesx($src); $h = imagesy($src);
    $r = min(1, $max / max($w, $h));
    $nw = max(1, (int)round($w * $r)); $nh = max(1, (int)round($h * $r));
    $dst = imagecreatetruecolor($nw, $nh);
    imagefill($dst, 0, 0, imagecolorallocate($dst, 255, 255, 255)); // priehľadnosť -> biela
    imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);
    $tmp = $thumb . '.tmp' . bin2hex(random_bytes(3));
    $ok = @imagejpeg($dst, $tmp, 82) && @rename($tmp, $thumb);
    if (!$ok) @unlink($tmp);
    return $ok ? $thumb : false;
}

/** Zmaže obrázok zo servera (PNG, metadáta aj náhľad) – iba pre správcu */
function gallery_delete($png) {
    $base = substr($png, 0, -4);
    $ok = @unlink($png);
    @unlink($base . '.json');
    @unlink($base . '.thumb.jpg');
    return $ok;
}

/** Ľudsky čitateľná veľkosť súboru */
function human_bytes($b) {
    return $b >= 1048576 ? round($b / 1048576, 1) . ' MB' : max(1, round($b / 1024)) . ' kB';
}


// =================================================================================
// Správca (prihlásenie heslom GALLERY_ADMIN_KEY cez session – heslo nikdy nie je v URL,
// takže sa nedostane do logov servera, histórie prehliadača ani do offline cache)
// =================================================================================

/** Je správca prihlásený? (zmena hesla v config.local.php automaticky odhlási) */
function is_admin() {
    return GALLERY_ADMIN_KEY !== ''
        && isset($_SESSION['md_admin'])
        && hash_equals(hash('sha256', 'md-admin|' . GALLERY_ADMIN_KEY), (string)$_SESSION['md_admin']);
}

/**
 * Prihlásenie správcu. Vracia true / false; po 5 neúspešných pokusoch je 10 minút pauza.
 */
function admin_login($password) {
    if (GALLERY_ADMIN_KEY === '') return false;
    $fail = $_SESSION['md_admin_fail'] ?? ['n' => 0, 't' => 0];
    if ($fail['n'] >= 5 && time() - $fail['t'] < 600) return false;

    if (is_string($password) && hash_equals(GALLERY_ADMIN_KEY, $password)) {
        session_regenerate_id(true);
        $_SESSION['md_admin'] = hash('sha256', 'md-admin|' . GALLERY_ADMIN_KEY);
        unset($_SESSION['md_admin_fail']);
        return true;
    }
    usleep(800000); // spomalenie hádania hesla
    $_SESSION['md_admin_fail'] = ['n' => ($fail['n'] >= 5 ? 0 : $fail['n']) + 1, 't' => time()];
    return false;
}

/** Zostávajúce minúty blokovania po neúspešných pokusoch (0 = neblokované) */
function admin_lock_minutes() {
    $fail = $_SESSION['md_admin_fail'] ?? ['n' => 0, 't' => 0];
    return ($fail['n'] >= 5 && time() - $fail['t'] < 600) ? (int)ceil((600 - (time() - $fail['t'])) / 60) : 0;
}

function admin_logout() {
    unset($_SESSION['md_admin']);
    session_regenerate_id(true);
}

/** CSRF token pre formuláre a POST požiadavky správcu */
function csrf_token() {
    if (empty($_SESSION['md_csrf'])) $_SESSION['md_csrf'] = bin2hex(random_bytes(16));
    return $_SESSION['md_csrf'];
}

function csrf_ok($token) {
    return !empty($_SESSION['md_csrf']) && is_string($token) && hash_equals($_SESSION['md_csrf'], $token);
}

/** Stránky správcu sa nesmú ukladať do cache (ani do offline cache PWA) */
function no_store_headers() {
    if (headers_sent()) return;
    header('Cache-Control: no-store, private');
    header('X-Robots-Tag: noindex, nofollow, noarchive');
    header('Referrer-Policy: no-referrer');
}


// =================================================================================
// Log generovania obrázkov
//
// Zapisujú sa IBA údaje o obrázku a hrubý typ zariadenia. Neukladá sa IP adresa,
// User-Agent, jazyk, časové pásmo, rozlíšenie, hardvér, sieť, referrer ani adresa stránky.
// Prehliadač je v logu len ako krátke anonymné ID (jednosmerný hash), ktoré sa nedá
// spätne priradiť ku konkrétnemu človeku – slúži iba na rozlíšenie "rôznych prehliadačov".
// =================================================================================

/** Krátke anonymné ID prehliadača pre log (hash z hashu, nedá sa spätne zistiť) */
function log_pseudonym($clientHash) {
    return $clientHash === '' ? '–' : substr(hash('sha256', 'md-log|' . $clientHash), 0, 8);
}

/**
 * Hrubé zaradenie klienta – iba rodina prehliadača / systému a typ zariadenia,
 * bez verzií, modelu zariadenia a celého User-Agent reťazca.
 */
function client_summary($ua) {
    $ua = (string)$ua;
    $browser = 'Iný';
    foreach ([
        '/Edg(?:A|iOS)?\//'   => 'Edge',
        '/OPR\//'             => 'Opera',
        '/SamsungBrowser\//'  => 'Samsung Internet',
        '/Firefox\/|FxiOS\//' => 'Firefox',
        '/Chrome\/|CriOS\//'  => 'Chrome',
        '/Safari\//'          => 'Safari',
    ] as $re => $name) {
        if (preg_match($re, $ua)) { $browser = $name; break; }
    }

    $os = 'Iný';
    if (stripos($ua, 'Windows') !== false)                 $os = 'Windows';
    elseif (stripos($ua, 'Android') !== false)             $os = 'Android';
    elseif (preg_match('/iPhone|iPad|iPod/', $ua))         $os = 'iOS / iPadOS';
    elseif (stripos($ua, 'Mac OS X') !== false)            $os = 'macOS';
    elseif (stripos($ua, 'CrOS') !== false)                $os = 'ChromeOS';
    elseif (stripos($ua, 'Linux') !== false)               $os = 'Linux';

    $device = 'Počítač';
    if (preg_match('/iPad|Tablet/i', $ua) || (stripos($ua, 'Android') !== false && stripos($ua, 'Mobile') === false)) $device = 'Tablet';
    elseif (preg_match('/Mobile|iPhone|iPod/i', $ua)) $device = 'Mobil';

    return ['browser' => $browser, 'os' => $os, 'device' => $device];
}

/** Pripraví priečinok logov (vrátane zákazu prístupu z webu) */
function ensure_log_dir() {
    if (!is_dir(LOG_DIR)) @mkdir(LOG_DIR, 0775, true);
    if (!is_file(LOG_DIR . '.htaccess')) {
        @file_put_contents(LOG_DIR . '.htaccess', "Require all denied\n<IfModule !mod_authz_core.c>\n    Order allow,deny\n    Deny from all\n</IfModule>\n");
    }
    if (!is_file(LOG_DIR . 'index.html')) @file_put_contents(LOG_DIR . 'index.html', '');
    return is_dir(LOG_DIR) && is_writable(LOG_DIR);
}

/**
 * Zapíše záznam o vygenerovanom obrázku do mesačného TXT logu
 */
function write_generation_log(array $entry) {
    if (!LOG_ENABLED || !ensure_log_dir()) return;
    scrub_legacy_logs();

    $client = client_summary($_SERVER['HTTP_USER_AGENT'] ?? '');
    $now = new DateTime('now', new DateTimeZone('Europe/Bratislava'));

    $clean = fn($v) => trim(preg_replace('/[\r\n\t]+/', ' ', mb_substr((string)$v, 0, 300, 'UTF-8')));
    // zarovnanie podľa počtu znakov (nie bajtov), aby diakritika neposúvala stĺpce
    $line = fn($label, $value) => ($value === '' || $value === null) ? '' : '  ' . $label . ':' . str_repeat(' ', max(1, 23 - mb_strlen($label, 'UTF-8'))) . $clean($value) . "\n";

    $startDate = '';
    if (!empty($entry['startDate'])) {
        try { $startDate = (new DateTime($entry['startDate']))->setTimezone(new DateTimeZone('Europe/Bratislava'))->format('j.n.Y H:i'); }
        catch (Throwable $e) { $startDate = ''; }
    }

    $txt  = str_repeat('=', 78) . "\n";
    $isSquad = !in_array($entry['type'] ?? '', ['matchday', 'lineup', 'result', 'goals', 'report', 'h2h', 'form'], true);
    $title = $isSquad ? (($entry['player'] ?? '') !== '' ? $entry['player'] . ' – ' : '') . $entry['d1name'] . (($entry['category'] ?? '') !== '' ? ' – ' . $entry['category'] : '') . (($entry['season'] ?? '') !== '' ? ' (' . $entry['season'] . ')' : '')
                      : $entry['d1name'] . ' vs ' . $entry['d2name'];
    $txt .= $now->format('Y-m-d H:i:s') . '  |  ' . $clean($entry['typeName']) . '  |  ' . $clean($title) . "\n";
    $txt .= str_repeat('-', 78) . "\n";
    $txt .= "OBRÁZOK\n";
    $txt .= $line('Typ', $entry['typeName'] . ' (' . $entry['type'] . ')');
    if ($isSquad) $txt .= $line('Družstvo', $title);
    else $txt .= $line('Zápas', $entry['d1name'] . ' vs ' . $entry['d2name'] . (($entry['score1'] ?? '') !== '' ? '  ' . $entry['score1'] . ':' . $entry['score2'] : ''));
    $txt .= $line('Zostava tímu', ($entry['type'] ?? '') === 'lineup' ? ($entry['variant'] === 'away' ? $entry['d2name'] : $entry['d1name']) : '');
    $txt .= $line('Dátum zápasu', $startDate);
    $txt .= $line('Súťaž / kolo', trim(($entry['competition'] ?? '') . ' / ' . ($entry['round'] ?? ''), ' /'));
    $txt .= $line('ID zápasu', $entry['matchID'] ?? '');
    $txt .= $line('Súbor', $entry['file'] . '  (' . $entry['width'] . '×' . $entry['height'] . ' px, ' . human_bytes($entry['bytes']) . ')');
    $txt .= $line('Akcia', ($entry['replaced'] ? 'prepísaný existujúci' : 'nový') . ', ' . (($_SERVER['HTTP_X_MD_MODE'] ?? '') === 'manual' ? 'uložené ručne' : 'uložené automaticky'));
    $txt .= "ZARIADENIE (anonymne)\n";
    $txt .= $line('Anonymné ID', log_pseudonym($entry['client'] ?? ''));
    $txt .= $line('Prehliadač', $client['browser']);
    $txt .= $line('Systém', $client['os']);
    $txt .= $line('Zariadenie', $client['device']);
    $txt .= "\n";

    $file = LOG_DIR . 'generovanie-' . $now->format('Y-m') . '.txt';
    $isNew = !is_file($file);
    @file_put_contents($file, ($isNew ? "LOG GENEROVANIA OBRÁZKOV – Match Day – " . $now->format('m/Y') . "\n\n" : '') . $txt, FILE_APPEND | LOCK_EX);

    // upratovanie starých logov (raz za mesiac pri vytvorení nového súboru)
    if ($isNew && LOG_RETENTION_MONTHS > 0) {
        $limit = (clone $now)->modify('first day of -' . LOG_RETENTION_MONTHS . ' months')->format('Y-m');
        foreach (glob(LOG_DIR . 'generovanie-*.txt') ?: [] as $f) {
            if (preg_match('/generovanie-(\d{4}-\d{2})\.txt$/', $f, $m) && $m[1] < $limit) @unlink($f);
        }
    }
}

/**
 * Jednorazovo vyčistí staršie logy, ktoré ešte obsahovali IP adresy, User-Agent,
 * jazyk, časové pásmo, údaje o hardvéri, sieti, referrer a pod.
 * Ponechá iba údaje o obrázku a hrubý typ zariadenia (rovnako ako nové záznamy).
 * Po vyčistení vytvorí značku storage/logs/.scrubbed-v2, takže sa už nespúšťa.
 */
function scrub_legacy_logs($force = false) {
    $marker = LOG_DIR . '.scrubbed-v2';
    if (!$force && is_file($marker)) return 0;
    if (!is_dir(LOG_DIR) || !is_writable(LOG_DIR)) return 0;

    $keep = ['Typ', 'Zápas', 'Družstvo', 'Zostava tímu', 'Dátum zápasu', 'Súťaž / kolo', 'ID zápasu', 'Súbor', 'Akcia', 'Anonymné ID'];
    $fmt = fn($label, $value) => '  ' . $label . ':' . str_repeat(' ', max(1, 23 - mb_strlen($label, 'UTF-8'))) . $value;
    $changed = 0;

    foreach (glob(LOG_DIR . 'generovanie-*.txt') ?: [] as $file) {
        $src = (string)@file_get_contents($file);
        if ($src === '') continue;
        $out = [];
        foreach (preg_split('/\r?\n/', $src) as $row) {
            if ($row === 'KTO') { $out[] = 'ZARIADENIE (anonymne)'; continue; }
            if ($row === 'ZARIADENIE') continue;                      // stará sekcia s hardvérom
            if (!preg_match('/^  ([^:]+):\s+(.*)$/u', $row, $m)) { $out[] = $row; continue; }

            [$label, $value] = [trim($m[1]), trim($m[2])];
            if (in_array($label, $keep, true)) {
                $out[] = $row;
            } elseif ($label === 'ID prehliadača') {
                $out[] = $fmt('Anonymné ID', log_pseudonym(rtrim($value, '…')));
            } elseif ($label === 'Prehliadač') {
                $out[] = $fmt('Prehliadač', trim(preg_replace('/\s*[\d.]+.*$/', '', $value)) ?: 'Iný');
            } elseif ($label === 'Systém') {
                // len rodina systému, bez verzie a architektúry
                $fam = preg_match('/^(Windows|Android|iOS|iPadOS|macOS|ChromeOS|Linux)/', $value, $x) ? $x[1] : 'Iný';
                $out[] = $fmt('Systém', in_array($fam, ['iOS', 'iPadOS'], true) ? 'iOS / iPadOS' : $fam);
            } elseif ($label === 'Zariadenie') {
                $out[] = $fmt('Zariadenie', trim(preg_replace('/\s*\(.*$/', '', $value)));
            }
            // všetko ostatné (IP adresa, User-Agent, jazyk, časové pásmo, obrazovka, sieť, referrer…) sa vynechá
        }
        $new = implode("\n", $out);
        if ($new !== $src) {
            $tmp = $file . '.tmp' . bin2hex(random_bytes(3));
            if (@file_put_contents($tmp, $new, LOCK_EX) !== false && @rename($tmp, $file)) $changed++;
            else @unlink($tmp);
        }
    }
    @file_put_contents($marker, date('c') . "\n");
    return $changed;
}


// =================================================================================
// Sportnet – zostavy zápasu a súpiska družstva
//
// Formát dát Sportnetu nie je verejne zdokumentovaný, preto parser skúša viac
// možných názvov polí (napr. additionalData.nr / nr / number). Ak sa formát zmení,
// stačí upraviť zoznamy ciest nižšie. Správca vidí surovú štruktúru cez
// api-match?id=…&debug=1.
// =================================================================================

/** Hodnota z vnoreného poľa podľa prvej existujúcej cesty ("a.b.c") */
function sn_val($arr, array $paths, $default = null) {
    foreach ($paths as $path) {
        $v = $arr;
        foreach (explode('.', $path) as $k) {
            if (!is_array($v) || !array_key_exists($k, $v)) { $v = null; break; }
            $v = $v[$k];
        }
        if ($v !== null && $v !== '' && $v !== []) return $v;
    }
    return $default;
}

/** Meno osoby z rôznych tvarov objektu */
function sn_person_name($p) {
    if (is_string($p)) return trim($p);
    if (!is_array($p)) return '';
    $name = sn_val($p, ['sportnetUser.name', 'name', 'displayName', 'athlete.name', 'athlete.sportnetUser.name', 'user.name', 'person.name', 'player.name']);
    if (is_string($name) && trim($name) !== '') return trim(preg_replace('/\s+/u', ' ', $name));
    $first = sn_val($p, ['sportnetUser.givenname', 'givenname', 'firstName', 'firstname', 'name.first']);
    $last  = sn_val($p, ['sportnetUser.familyname', 'familyname', 'surname', 'lastName', 'lastname', 'name.last']);
    return trim(($first ?: '') . ' ' . ($last ?: ''));
}

/** ID osoby (na spájanie hráča naprieč zápasmi a s gólmi) */
function sn_person_id($p) {
    if (!is_array($p)) return '';
    return (string)sn_val($p, ['sportnetUser._id', 'sportnetId', 'athlete.sportnetUser._id', 'athlete._id', '_id', 'id', 'user._id'], '');
}

/** Normalizovaný kľúč mena (bez diakritiky a veľkosti písmen) */
function sn_name_key($name) {
    return slugify($name, 80);
}

/** Pravdivostná hodnota z rôznych zápisov (true, 1, "true", "ano") */
function sn_bool($v) {
    if (is_bool($v)) return $v;
    if (is_numeric($v)) return (int)$v === 1;
    return in_array(mb_strtolower(trim((string)$v), 'UTF-8'), ['true', 'yes', 'ano', 'áno', 'y'], true);
}

/** Jeden hráč zo zápisu -> {id, nr, name, sub, captain, gk} */
function sn_player($p) {
    $name = sn_person_name($p);
    if ($name === '') return null;
    $nr = sn_val($p, ['additionalData.nr', 'nr', 'number', 'dressNumber', 'shirtNumber', 'jerseyNumber', 'additionalData.number', 'additionalData.dressNumber']);
    $pos = (string)sn_val($p, ['additionalData.position', 'position', 'role', 'additionalData.role'], '');
    $subRaw = sn_val($p, ['additionalData.substitute', 'substitute', 'isSubstitute', 'additionalData.isSubstitute'], null);
    $photo = sn_val($p, ['sportnetUser.photo.public_url', 'sportnetUser.photo', 'sportnetUser.imagePublicUrl', 'sportnetUser.image', 'photo', 'imagePublicUrl', 'image', 'avatar'], '');
    return [
        'id'      => sn_person_id($p),
        'nr'      => is_scalar($nr) ? trim((string)$nr) : '',
        'name'    => $name,
        'sub'     => $subRaw !== null && sn_bool($subRaw),
        'subFlag' => $subRaw !== null,
        'photo'   => is_string($photo) && preg_match('#^https?://#', $photo) ? $photo : '',
        'captain' => sn_bool(sn_val($p, ['additionalData.captain', 'captain', 'isCaptain', 'additionalData.isCaptain'], false)),
        'gk'      => sn_bool(sn_val($p, ['additionalData.goalkeeper', 'goalkeeper', 'isGoalkeeper', 'additionalData.isGoalkeeper'], false))
                     || (bool)preg_match('/brank|goal|^gk$/iu', $pos),
    ];
}

/** Tréner z "crew" nominácie (objekt alebo zoznam) */
function sn_coach($crew) {
    if (!is_array($crew) || !$crew) return '';
    $isCoach = fn($s) => (bool)preg_match('/coach|tr[eé]ner|manager|head/iu', (string)$s);
    // objekt { coach: {...}, assistant: {...} }
    foreach ($crew as $k => $v) {
        if (is_string($k) && $isCoach($k) && !preg_match('/assist|asist/i', $k)) {
            $n = sn_person_name(is_array($v) && isset($v[0]) ? $v[0] : $v);
            if ($n !== '') return $n;
        }
    }
    // zoznam [{ position: "coach", sportnetUser: {...} }] – hlavný tréner má pozíciu presne "coach"
    foreach ($crew as $v) {
        if (is_array($v) && strtolower((string)sn_val($v, ['position', 'role', 'type'], '')) === 'coach') {
            $n = sn_person_name($v);
            if ($n !== '') return $n;
        }
    }
    foreach ($crew as $v) {
        if (!is_array($v)) continue;
        $pos = (string)sn_val($v, ['position', 'role', 'type', 'additionalData.position'], '');
        if ($isCoach($pos) && !preg_match('/assist|asist/iu', $pos)) {
            $n = sn_person_name($v);
            if ($n !== '') return $n;
        }
    }
    return '';
}

/**
 * Zostavy oboch tímov zo zápasu.
 * @return array ['home' => ['start'=>[], 'subs'=>[], 'coach'=>''], 'away' => …]
 */
function sportnet_lineups(array $match, $homeId = '', $awayId = '') {
    $empty = ['start' => [], 'subs' => [], 'coach' => '', 'flagged' => false, 'guessed' => false];
    $out = ['home' => $empty, 'away' => $empty];
    $homeId = $homeId ?: (string)sn_val($match, ['homeTeam._id', 'teams.0._id'], '');
    $awayId = $awayId ?: (string)sn_val($match, ['awayTeam._id', 'teams.1._id'], '');

    $noms = sn_val($match, ['nominations', 'nomination', 'lineups', 'protocol.nominations'], []);
    if (!is_array($noms)) return $out;
    $list = [];
    foreach ($noms as $key => $n) {
        if (!is_array($n)) continue;
        $tid = (string)sn_val($n, ['teamId', 'team._id', 'team.id', 'team'], is_string($key) ? $key : '');
        $list[] = [$tid, $n];
    }
    // strana podľa ID družstva; nominácia bez zhody dostane stranu, ktorá ešte ostala voľná
    $sides = [];
    foreach ($list as $i => [$tid]) $sides[$i] = $tid !== '' && $tid === $homeId ? 'home' : ($tid !== '' && $tid === $awayId ? 'away' : null);
    foreach ($sides as $i => $sd) {
        if ($sd !== null || count($list) !== 2) continue;
        $other = $sides[1 - $i];
        $sides[$i] = $other === 'home' ? 'away' : ($other === 'away' ? 'home' : ($i === 0 ? 'home' : 'away'));
    }
    foreach ($list as $i => [$tid, $n]) {
        $side = $sides[$i];
        if ($side === null) continue;

        $players = sn_val($n, ['athletes', 'players', 'squad.athletes', 'nominations'], []);
        $all = [];
        foreach (is_array($players) ? $players : [] as $p) {
            $pl = sn_player($p);
            if ($pl) $all[] = $pl;
        }
        // Príznak náhradníka je v zápisoch vyplnený len zriedka (SFZ uvádza ~7 % hráčov).
        // Ak v tomto zápise nie je vyznačený nikde a hráčov je viac ako 11, rozdelí sa podľa poradia
        // v zápise (základ býva uvedený prvý) – v generátore sa to zobrazí ako odhad.
        $flagged = (bool)array_filter($all, fn($x) => $x['sub']);
        // bez príznaku: kto v zápise prišiel striedaním, je náhradník (udalosť "substitution")
        if (!$flagged && count($all) > 11 && ($rep = sn_replacements($match, $tid))) {
            foreach ($all as &$pl) if (isset($rep[$pl['id']]) || isset($rep['n:' . sn_name_key($pl['name'])])) $pl['sub'] = true;
            unset($pl);
            $flagged = count(array_filter($all, fn($x) => !$x['sub'])) <= 11;
            if (!$flagged) foreach ($all as &$pl) $pl['sub'] = false;
            unset($pl);
        }
        if (!$flagged && count($all) > 11) {
            foreach ($all as $i => $pl) { $pl['sub'] = $i >= 11; $out[$side][$pl['sub'] ? 'subs' : 'start'][] = $pl; }
            $out[$side]['guessed'] = true;
        } else {
            foreach ($all as $pl) $out[$side][$pl['sub'] ? 'subs' : 'start'][] = $pl;
        }
        $out[$side]['flagged'] = $flagged;
        $out[$side]['coach'] = sn_coach(sn_val($n, ['crew', 'staff', 'officials'], []));
    }
    // brankár v základe ako prvý (ak zápis nemá poradie)
    foreach (['home', 'away'] as $s) {
        usort($out[$s]['start'], fn($a, $b) => (int)$b['gk'] <=> (int)$a['gk']);
    }
    return $out;
}

/** Hráči, ktorí v zápase prišli na ihrisko striedaním (kľúče: ID aj "n:" + kľúč mena) */
function sn_replacements(array $match, $teamId = '') {
    $out = [];
    foreach ((array)sn_val($match, ['protocol.events', 'events'], []) as $e) {
        if (!is_array($e) || !preg_match('/subst|striedan/i', (string)($e['eventType'] ?? ''))) continue;
        if ($teamId !== '' && (string)($e['team'] ?? '') !== $teamId) continue;
        $in = $e['replacement'] ?? $e['playerIn'] ?? null;
        if (!is_array($in)) continue;
        $id = sn_person_id($in);
        if ($id !== '') $out[$id] = true;
        $out['n:' . sn_name_key(sn_person_name($in))] = true;
    }
    return $out;
}

/** Góly zo zápasu: [{minute, name, id, side, own}] */
function sportnet_goals(array $match, $homeId) {
    $goals = [];
    foreach ((array)sn_val($match, ['protocol.events'], []) as $e) {
        if (!is_array($e) || ($e['eventType'] ?? '') !== 'goal') continue;
        $own  = ($e['type'] ?? '') === 'dropped';
        $side = ($e['team'] ?? '') === $homeId ? 'home' : 'away';
        if ($own) $side = $side === 'home' ? 'away' : 'home';
        $player = $e['player'] ?? null;
        $goals[] = [
            'minute' => explode(':', (string)($e['eventTime'] ?? ''))[0],
            'name'   => sn_person_name($player),
            'id'     => sn_person_id(is_array($player) ? $player : []),
            'team'   => (string)($e['team'] ?? ''),
            'side'   => $side,
            'own'    => $own,
        ];
    }
    usort($goals, fn($a, $b) => (int)$a['minute'] <=> (int)$b['minute']);
    return $goals;
}

/** Priečinok dočasnej cache odpovedí Sportnetu */
function sn_cache_file($key) {
    $dir = sys_get_temp_dir() . '/match-day-cache';
    if (!is_dir($dir)) @mkdir($dir, 0775, true);
    return $dir . '/' . sha1($key) . '.json';
}

/** JSON z URL s cache na disku (ttl v sekundách); pri chybe vráti starú cache, ak existuje */
function sn_fetch_cached($url, $ttl) {
    $f = sn_cache_file($url);
    if (is_file($f) && filemtime($f) > time() - $ttl) {
        $d = json_decode((string)file_get_contents($f), true);
        if (is_array($d)) return $d;
    }
    try {
        $d = getJsonFromUrl($url);
    } catch (Throwable $e) {
        $d = null;
    }
    if (is_array($d) && empty($d['error'])) {
        @file_put_contents($f, json_encode($d, JSON_UNESCAPED_UNICODE), LOCK_EX);
        return $d;
    }
    if (is_file($f)) {
        $old = json_decode((string)file_get_contents($f), true);
        if (is_array($old)) return $old;
    }
    return is_array($d) ? $d : ['error' => true];
}

/** Viac JSON adries naraz (paralelne, s cache); vracia pole v rovnakom poradí */
function sn_fetch_many(array $urls, $ttl, $parallel = 6) {
    $out = [];
    $todo = [];
    foreach ($urls as $i => $url) {
        $f = sn_cache_file($url);
        if (is_file($f) && filemtime($f) > time() - $ttl && is_array($d = json_decode((string)file_get_contents($f), true))) $out[$i] = $d;
        else $todo[$i] = $url;
    }
    foreach (array_chunk($todo, $parallel, true) as $chunk) {
        $mh = curl_multi_init();
        $handles = [];
        foreach ($chunk as $i => $url) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true, CURLOPT_TIMEOUT => 20,
                CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120 Safari/537.36',
                CURLOPT_HTTPHEADER => ['Accept: application/json'],
                CURLOPT_SSL_VERIFYPEER => false, CURLOPT_SSL_VERIFYHOST => false,
            ]);
            curl_multi_add_handle($mh, $ch);
            $handles[$i] = $ch;
        }
        do {
            $st = curl_multi_exec($mh, $running);
            if ($running) curl_multi_select($mh, 1.0);
        } while ($running && $st === CURLM_OK);
        foreach ($handles as $i => $ch) {
            $body = curl_multi_getcontent($ch);
            $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_multi_remove_handle($mh, $ch);
            $d = $code === 200 ? json_decode((string)$body, true) : null;
            $f = sn_cache_file($chunk[$i]);
            if (is_array($d)) {
                @file_put_contents($f, json_encode($d, JSON_UNESCAPED_UNICODE), LOCK_EX);
                $out[$i] = $d;
            } elseif (is_file($f)) {
                $out[$i] = json_decode((string)file_get_contents($f), true) ?: null;
            } else {
                $out[$i] = null;
            }
        }
        curl_multi_close($mh);
    }
    ksort($out);
    return $out;
}

/**
 * ID "nášho" družstva v zozname zápasov – tím, ktorý sa vyskytuje v najviac zápasoch
 * (zoznam sa načítava podľa ID družstva, takže je v každom zápase).
 * @return array [id, name, logoUrl]
 */
function sn_own_team(array $matches, $preferId = '') {
    $count = [];
    $info = [];
    foreach ($matches as $m) {
        foreach (['homeTeam', 'awayTeam'] as $s) {
            $id = (string)($m[$s]['_id'] ?? '');
            if ($id === '') continue;
            $count[$id] = ($count[$id] ?? 0) + 1;
            if (empty($info[$id]['logo']) || empty($info[$id]['name'])) {
                $info[$id] = ['name' => $m[$s]['name'] ?? ($info[$id]['name'] ?? ''), 'logo' => $m[$s]['logoUrl'] ?? ($info[$id]['logo'] ?? '')];
            }
        }
    }
    if ($preferId !== '' && isset($count[$preferId])) $id = $preferId;
    else { arsort($count); $id = (string)array_key_first($count); }
    return [$id, $info[$id]['name'] ?? '', $info[$id]['logo'] ?? ''];
}

/**
 * Súpiska družstva za sezónu (pre obrázok Súpiska) – zjednodušený výťah zo sportnet_team().
 */
function sportnet_squad($teamId) {
    $t = sportnet_team($teamId);
    if (!empty($t['error'])) return $t;
    $players = array_map(fn($p) => ['nr' => $p['nr'], 'name' => $p['name'], 'apps' => $p['apps'], 'starts' => $p['starts'], 'minutes' => $p['minutes'], 'goals' => $p['goals'],
        'gk' => $p['gk'], 'captain' => $p['captain'], 'key' => $p['key']], array_values(array_filter($t['players'], fn($p) => $p['apps'] > 0 || ($p['listed'] ?? 0) > 0)));
    return ['players' => $players, 'coach' => $t['coach'], 'team' => $t['team']['name'], 'teamId' => $t['team']['id'], 'matches' => $t['matchesUsed'], 'played' => $t['played']];
}

require_once __DIR__ . '/sportnet.php';
