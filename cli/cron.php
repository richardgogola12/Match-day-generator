<?php
/**
 * Nočná úloha z príkazového riadku (cron), napr. každý deň o 4:10:
 *   10 4 * * *  php /cesta/k/match-day/cli/cron.php > /dev/null 2>&1
 */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Iba z príkazového riadku.'); }
$_SERVER['HTTP_HOST'] = $_SERVER['HTTP_HOST'] ?? 'localhost';
require __DIR__ . '/../config.php';
require INCLUDES_DIR . 'functions.php';
require_once INCLUDES_DIR . 'cron.php';
md_cron(function ($m) { echo $m, PHP_EOL; });
