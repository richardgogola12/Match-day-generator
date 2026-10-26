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
    if ($status >= 400 && !empty($GLOBALS['php_warnings'])) {
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
    return ['matchday' => 'Match day', 'result' => 'Výsledok', 'goals' => 'Strelci'];
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

/** Je zadané správne admin heslo galérie? */
function gallery_is_admin($key) {
    return GALLERY_ADMIN_KEY !== '' && is_string($key) && hash_equals(GALLERY_ADMIN_KEY, $key);
}

/**
 * Zoznam uložených obrázkov (najnovšie prvé)
 * @param string|null $client hash prehliadača; null = všetky (admin)
 */
function gallery_items($client = null) {
    $items = [];
    foreach (glob(STORAGE_DIR . '*/*.json') ?: [] as $jsonFile) {
        $meta = json_decode((string)@file_get_contents($jsonFile), true);
        $png = substr($jsonFile, 0, -5) . '.png';
        if (!is_array($meta) || !is_file($png)) continue;
        if ($client !== null && (($meta['client'] ?? '') !== $client || !empty($meta['hidden']))) continue;
        $rel = basename(dirname($png)) . '/' . basename($png);
        $meta['file'] = $rel;
        $meta['url'] = STORAGE_URL . $rel . '?v=' . filemtime($png);
        $meta['bytes'] = filesize($png);
        unset($meta['client']);
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

/** Ľudsky čitateľná veľkosť súboru */
function human_bytes($b) {
    return $b >= 1048576 ? round($b / 1048576, 1) . ' MB' : max(1, round($b / 1024)) . ' kB';
}


// =================================================================================
// Log generovania obrázkov
// =================================================================================

/**
 * IP adresa klienta. REMOTE_ADDR je spoľahlivá; hlavičky proxy sa zapisujú zvlášť
 * (klient si ich môže podvrhnúť, ale za Cloudflare/proxy obsahujú skutočnú IP).
 */
function client_ip_info() {
    $out = ['ip' => $_SERVER['REMOTE_ADDR'] ?? '?'];
    foreach (['HTTP_CF_CONNECTING_IP' => 'cf', 'HTTP_X_FORWARDED_FOR' => 'forwarded', 'HTTP_X_REAL_IP' => 'real'] as $h => $k) {
        if (!empty($_SERVER[$h])) $out[$k] = substr($_SERVER[$h], 0, 200);
    }
    return $out;
}

/**
 * Rozpozná prehliadač, systém a typ zariadenia z User-Agent (+ Client Hints, ak sú)
 */
function parse_client($ua, $info = []) {
    $browser = 'Neznámy';
    $patterns = [
        '/Edg(?:A|iOS)?\/([\d.]+)/'        => 'Microsoft Edge',
        '/OPR\/([\d.]+)/'                  => 'Opera',
        '/SamsungBrowser\/([\d.]+)/'       => 'Samsung Internet',
        '/YaBrowser\/([\d.]+)/'            => 'Yandex',
        '/Vivaldi\/([\d.]+)/'              => 'Vivaldi',
        '/Firefox\/([\d.]+)/'              => 'Firefox',
        '/FxiOS\/([\d.]+)/'                => 'Firefox (iOS)',
        '/CriOS\/([\d.]+)/'                => 'Chrome (iOS)',
        '/Chrome\/([\d.]+)/'               => 'Chrome',
        '/Version\/([\d.]+).*Safari/'      => 'Safari',
    ];
    foreach ($patterns as $re => $name) {
        if (preg_match($re, $ua, $m)) { $browser = $name . ' ' . $m[1]; break; }
    }
    // presnejšia verzia z Client Hints
    if (!empty($info['chFullVersions']) && preg_match('/(Microsoft Edge|Opera|Google Chrome|Brave|Vivaldi) ([\d.]+)/', $info['chFullVersions'], $m)) {
        $browser = str_replace('Google ', '', $m[1]) . ' ' . $m[2];
    }

    $os = 'Neznámy';
    if (preg_match('/Windows NT ([\d.]+)/', $ua, $m)) {
        $os = ['10.0' => 'Windows 10/11', '6.3' => 'Windows 8.1', '6.2' => 'Windows 8', '6.1' => 'Windows 7'][$m[1]] ?? 'Windows NT ' . $m[1];
    } elseif (preg_match('/Android ([\d.]+)/', $ua, $m)) {
        $os = 'Android ' . $m[1];
    } elseif (preg_match('/(iPhone|iPad|iPod).*OS ([\d_]+)/', $ua, $m)) {
        $os = ($m[1] === 'iPad' ? 'iPadOS ' : 'iOS ') . str_replace('_', '.', $m[2]);
    } elseif (preg_match('/Mac OS X ([\d_.]+)/', $ua, $m)) {
        $os = 'macOS ' . str_replace('_', '.', $m[1]);
    } elseif (stripos($ua, 'CrOS') !== false) {
        $os = 'ChromeOS';
    } elseif (stripos($ua, 'Linux') !== false) {
        $os = 'Linux';
    }
    // Client Hints vedia rozlíšiť Windows 10 a 11 a presnú verziu systému
    $p = $info['chPlatform'] ?? '';
    $pv = $info['chPlatformVersion'] ?? '';
    if ($p === 'Windows' && $pv !== '') {
        $major = (int)explode('.', $pv)[0];
        $os = ($major >= 13 ? 'Windows 11' : ($major > 0 ? 'Windows 10' : 'Windows 7/8')) . ' (verzia ' . $pv . ')';
    } elseif ($p === 'macOS' && $pv !== '') {
        $os = 'macOS ' . $pv;
    } elseif ($p === 'Android' && $pv !== '') {
        $os = 'Android ' . $pv;
    } elseif ($p !== '' && $pv !== '' && $os === 'Neznámy') {
        $os = $p . ' ' . $pv;
    }
    // architektúru pridať, len ak Client Hints zodpovedajú systému z User-Agent
    $osFamily = ['Windows' => 'Windows', 'macOS' => 'macOS', 'Linux' => 'Linux', 'Android' => 'Android', 'Chrome OS' => 'ChromeOS'][$p] ?? null;
    if (!empty($info['chArchitecture']) && $osFamily && str_starts_with($os, $osFamily)) $os .= ', ' . $info['chArchitecture'] . (!empty($info['chBitness']) ? ' ' . $info['chBitness'] . '-bit' : '');

    $device = 'Počítač';
    if (preg_match('/iPad|Tablet/i', $ua) || (stripos($ua, 'Android') !== false && stripos($ua, 'Mobile') === false)) $device = 'Tablet';
    elseif (preg_match('/Mobile|iPhone|iPod/i', $ua) || !empty($info['uaMobile'])) $device = 'Mobil';
    if (!empty($info['chModel'])) $device .= ' (' . $info['chModel'] . ')';
    elseif (preg_match('/Android [\d.]+; ([^;)]+?)(?: Build|\))/', $ua, $m) && strtolower(trim($m[1])) !== 'k') $device .= ' (' . trim($m[1]) . ')';

    return ['browser' => $browser, 'os' => $os, 'device' => $device];
}

/**
 * Zapíše záznam o vygenerovanom obrázku do mesačného TXT logu
 */
function write_generation_log(array $entry) {
    if (!LOG_ENABLED) return;
    if (!is_dir(LOG_DIR)) @mkdir(LOG_DIR, 0775, true);
    if (!is_file(LOG_DIR . '.htaccess')) {
        @file_put_contents(LOG_DIR . '.htaccess', "Require all denied\n<IfModule !mod_authz_core.c>\n    Order allow,deny\n    Deny from all\n</IfModule>\n");
        @file_put_contents(LOG_DIR . 'index.html', '');
    }

    // údaje z prehliadača (hlavička X-MD-Info = base64 JSON)
    $info = [];
    $raw = $_SERVER['HTTP_X_MD_INFO'] ?? '';
    if ($raw !== '' && strlen($raw) < 16000) {
        $dec = json_decode((string)base64_decode($raw, true), true);
        if (is_array($dec)) $info = $dec;
    }
    $ua = (string)($_SERVER['HTTP_USER_AGENT'] ?? ($info['userAgent'] ?? ''));
    $client = parse_client($ua, $info);
    $ipInfo = client_ip_info();
    $now = new DateTime('now', new DateTimeZone('Europe/Bratislava'));

    $clean = fn($v) => is_bool($v) ? ($v ? 'áno' : 'nie') : trim(preg_replace('/[\r\n\t]+/', ' ', mb_substr((string)$v, 0, 500, 'UTF-8')));
    // zarovnanie podľa počtu znakov (nie bajtov), aby diakritika neposúvala stĺpce
    $line = fn($label, $value) => ($value === '' || $value === null) ? '' : '  ' . $label . ':' . str_repeat(' ', max(1, 23 - mb_strlen($label, 'UTF-8'))) . $clean($value) . "\n";

    $ip = $ipInfo['ip'];
    foreach (['cf' => 'Cloudflare', 'forwarded' => 'X-Forwarded-For', 'real' => 'X-Real-IP'] as $k => $l) {
        if (isset($ipInfo[$k])) $ip .= '  (' . $l . ': ' . $ipInfo[$k] . ')';
    }

    $txt  = str_repeat('=', 78) . "\n";
    $txt .= $now->format('Y-m-d H:i:s') . '  |  ' . $entry['typeName'] . '  |  ' . $entry['d1name'] . ' vs ' . $entry['d2name'] . "\n";
    $txt .= str_repeat('-', 78) . "\n";
    $txt .= "OBRÁZOK\n";
    $txt .= $line('Typ', $entry['typeName'] . ' (' . $entry['type'] . ')');
    $txt .= $line('Zápas', $entry['d1name'] . ' vs ' . $entry['d2name'] . (($entry['score1'] ?? '') !== '' ? '  ' . $entry['score1'] . ':' . $entry['score2'] : ''));
    $txt .= $line('Dátum zápasu', $entry['startDate'] ? (new DateTime($entry['startDate']))->setTimezone(new DateTimeZone('Europe/Bratislava'))->format('j.n.Y H:i') : '');
    $txt .= $line('Súťaž / kolo', trim(($entry['competition'] ?? '') . ' / ' . ($entry['round'] ?? ''), ' /'));
    $txt .= $line('ID zápasu', $entry['matchID'] ?? '');
    $txt .= $line('Súbor', $entry['file'] . '  (' . $entry['width'] . '×' . $entry['height'] . ' px, ' . human_bytes($entry['bytes']) . ')');
    $txt .= $line('Akcia', ($entry['replaced'] ? 'prepísaný existujúci' : 'nový') . ', ' . (($_SERVER['HTTP_X_MD_MODE'] ?? '') === 'manual' ? 'uložené ručne' : 'uložené automaticky'));
    $txt .= "KTO\n";
    $txt .= $line('IP adresa', $ip);
    $txt .= $line('ID prehliadača', substr($entry['client'], 0, 16) . '…');
    $txt .= $line('Prehliadač', $client['browser']);
    $txt .= $line('Systém', $client['os']);
    $txt .= $line('Zariadenie', $client['device']);
    $txt .= $line('User-Agent', $ua);
    $txt .= $line('Jazyk', ($info['language'] ?? '') . (($info['languages'] ?? '') !== '' ? '  [' . $info['languages'] . ']' : ''));
    $txt .= $line('Accept-Language', $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '');
    $txt .= $line('Časové pásmo', ($info['timezone'] ?? '') . (isset($info['tzOffsetMin']) ? sprintf(' (UTC%+d:%02d)', intdiv($info['tzOffsetMin'], 60), abs($info['tzOffsetMin']) % 60) : ''));
    $txt .= $line('Čas na zariadení', $info['localTime'] ?? '');
    if ($info) {
        $txt .= "ZARIADENIE\n";
        if (!empty($info['screen'])) $txt .= $line('Obrazovka', ($info['screen'] ?? '') . ' (dostupné ' . ($info['screenAvail'] ?? '') . '), ' . ($info['colorDepth'] ?? '') . '-bit, DPR ' . ($info['pixelRatio'] ?? ''));
        $txt .= $line('Okno prehliadača', ($info['viewport'] ?? '') . (($info['orientation'] ?? '') !== '' ? ', ' . $info['orientation'] : ''));
        $txt .= $line('Procesor (jadrá)', $info['cpuCores'] ?? '');
        $txt .= $line('Pamäť RAM (GB)', ($info['memoryGB'] ?? '') !== '' ? '≥ ' . $info['memoryGB'] . ' (prehliadač zaokrúhľuje)' : '');
        $txt .= $line('Dotykové body', $info['touchPoints'] ?? '');
        $txt .= $line('Platforma', trim(($info['platform'] ?? '') . ' ' . ($info['vendor'] ?? '')));
        $txt .= $line('Značky (Client Hints)', $info['chFullVersions'] ?? ($info['uaBrands'] ?? ''));
        $txt .= $line('Typ zariadenia (CH)', $info['chFormFactors'] ?? '');
        $txt .= $line('Sieť', ($info['netType'] ?? '') !== '' ? $info['netType'] . ', ' . ($info['netDownlinkMbps'] ?? '?') . ' Mb/s, RTT ' . ($info['netRttMs'] ?? '?') . ' ms' . (!empty($info['saveData']) ? ', šetrenie dát' : '') : '');
        $txt .= $line('Nainštalovaná PWA', $info['pwaInstalled'] ?? '');
        $txt .= $line('Tmavý režim systému', $info['darkMode'] ?? '');
        $txt .= $line('Téma aplikácie', $info['appTheme'] ?? '');
        $txt .= $line('Do Not Track', $info['doNotTrack'] ?? '');
        $txt .= $line('Cookies povolené', $info['cookies'] ?? '');
        $txt .= $line('Úložisko webu (MB)', $info['storageUsedMB'] ?? '');
        $txt .= $line('Stránka', $info['page'] ?? '');
        $txt .= $line('Prišiel z (referrer)', $info['referrer'] ?? '');
    }
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
