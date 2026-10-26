<?php
// Adresy, ktoré vracajú JSON / obrázok / JS – výpis chýb by ich poškodil,
// preto sa tu chyby nikdy nevypisujú (idú iba do logu) a výstup sa bufferuje.
define('IS_MACHINE_REQUEST', (bool)preg_match(
    '#/(api-[a-z]+|generate-match-[a-z]+|sw\.js|manifest\.webmanifest)(\?|$)#',
    parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?? ''
));

error_reporting(E_ALL);
ini_set('display_errors', IS_MACHINE_REQUEST ? 0 : 1); // development server = 1; production server = 0
ini_set('display_startup_errors', IS_MACHINE_REQUEST ? 0 : 1);
ini_set('html_errors', IS_MACHINE_REQUEST ? 0 : 1);
ini_set('log_errors', 1);

if (IS_MACHINE_REQUEST) {
    ob_start();
    // varovania/deprecated si zapamätáme (pre diagnostiku v JSON chybe), ale nevypíšeme
    $GLOBALS['php_warnings'] = [];
    set_error_handler(function ($no, $str, $file, $line) {
        $GLOBALS['php_warnings'][] = $str . ' (' . basename($file) . ':' . $line . ')';
        error_log('match-day: ' . $str . ' in ' . $file . ':' . $line);
        return true;
    });
}

ini_set('memory_limit', '512M');

if (!session_id()) @session_start();

require __DIR__ . "/config.php";
require INCLUDES_DIR . "functions.php";

//parse first segment of url address
$page = segment(1);

//if no exists show error page
if (!array_key_exists($page, $routes)) show_404();

// otherwise include page content
show_page($routes[$page]);
