<?php
require __DIR__ . "/config.php";

// Adresy, ktoré vracajú JSON / obrázok / JS – výpis chýb by ich poškodil,
// preto sa tu chyby nikdy nevypisujú (idú iba do logu servera) a výstup sa bufferuje.
define('IS_MACHINE_REQUEST', (bool)preg_match(
    '#/(api-[a-z]+|generate-match-[a-z]+|sw\.js|manifest\.webmanifest)(\?|$)#',
    parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?? ''
));

// Chyby sa na stránke zobrazujú iba pri APP_DEBUG (vývoj); na produkcii idú len do logu servera,
// aby sa návštevníkom neukazovali cesty k súborom a iné interné údaje.
$__showErrors = APP_DEBUG && !IS_MACHINE_REQUEST;
error_reporting(E_ALL);
ini_set('display_errors', $__showErrors ? 1 : 0);
ini_set('display_startup_errors', $__showErrors ? 1 : 0);
ini_set('html_errors', $__showErrors ? 1 : 0);
ini_set('log_errors', 1);

if (IS_MACHINE_REQUEST) {
    ob_start();
    // varovania/deprecated si zapamätáme (pri APP_DEBUG sa pridajú k JSON chybe), ale nevypíšeme
    $GLOBALS['php_warnings'] = [];
    set_error_handler(function ($no, $str, $file, $line) {
        $GLOBALS['php_warnings'][] = $str . ' (' . basename($file) . ':' . $line . ')';
        error_log('match-day: ' . $str . ' in ' . basename($file) . ':' . $line);
        return true;
    });
}

ini_set('memory_limit', '512M');

// session iba pre prihlásenie správcu – bežnému návštevníkovi sa cookie vôbec nevytvára
session_name('md_sess');
$__needSession = isset($_COOKIE['md_sess'])
    || preg_match('#/spravca/?$#', parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?? '');
if ($__needSession && !session_id()) {
    $__https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => (BASE_PATH === '' ? '/' : BASE_PATH . '/'),
        'secure'   => $__https,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    ini_set('session.use_strict_mode', 1);
    ini_set('session.use_only_cookies', 1);
    @session_start();
}

require INCLUDES_DIR . "functions.php";
md_security_headers();

//parse first segment of url address
$page = segment(1);

//if no exists show error page
if (!array_key_exists($page, $routes)) show_404();

// otherwise include page content
show_page($routes[$page]);
