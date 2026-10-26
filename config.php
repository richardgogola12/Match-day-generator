<?php
// ---------------------------------------------------------------------------------
// LOKÁLNE / TAJNÉ NASTAVENIA
// Heslá a tajné kľúče patria do config.local.php (nie je v gite, viď config.local.example.php).
// ---------------------------------------------------------------------------------
if (is_file(__DIR__ . '/config.local.php')) require __DIR__ . '/config.local.php';

// ---------------------------------------------------------------------------------
// PROJECT NAME
// ---------------------------------------------------------------------------------
define('APP_NAME', 'Match Day Generator');

// ---------------------------------------------------------------------------------
// PROJECT BASE PATH (priečinok, v ktorom aplikácia beží, bez lomky na konci)
// ---------------------------------------------------------------------------------
define('BASE_PATH', '/match-day');

// ---------------------------------------------------------------------------------
// URL PATHS
// ---------------------------------------------------------------------------------
$__scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
define('ROOT_URL', $__scheme . '://' . ($_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost'));
define('APP_URL', ROOT_URL . BASE_PATH);

// ---------------------------------------------------------------------------------
// LOCAL PATHS
// ---------------------------------------------------------------------------------
define('APP_DIR', __DIR__);
define('INCLUDES_DIR', APP_DIR . '/includes/');
define('PAGES_DIR', APP_DIR . '/pages/');
define('LAYOUT_DIR', INCLUDES_DIR . 'layout/');
define('ASSETS_DIR', APP_DIR . '/assets/');
define('CLASSES_DIR', INCLUDES_DIR . 'classes/');

// ---------------------------------------------------------------------------------
// ULOŽENÉ OBRÁZKY (galéria)
// ---------------------------------------------------------------------------------
define('STORAGE_DIR', APP_DIR . '/storage/generated/');      // priečinok musí byť zapisovateľný pre PHP
define('STORAGE_URL', BASE_PATH . '/storage/generated/');
define('MAX_IMAGE_BYTES', 15 * 1024 * 1024);                // max. veľkosť jedného PNG
define('MAX_IMAGE_SIDE', 4000);                             // max. šírka / výška v px
// Galéria zobrazuje iba obrázky vytvorené v danom prehliadači; na serveri sa ukladajú všetky.
// Ak nastavíte heslo, na adrese galeria?admin=HESLO uvidíte obrázky zo všetkých prehliadačov.
defined('GALLERY_ADMIN_KEY') || define('GALLERY_ADMIN_KEY', '');   // nastavte v config.local.php

// ---------------------------------------------------------------------------------
// LOG GENEROVANIA (storage/logs/generovanie-RRRR-MM.txt, z webu nedostupné)
// ---------------------------------------------------------------------------------
defined('LOG_ENABLED') || define('LOG_ENABLED', true);
define('LOG_DIR', APP_DIR . '/storage/logs/');
define('LOG_RETENTION_MONTHS', 12);                        // staršie mesačné logy sa automaticky zmažú (0 = nikdy)
// Tajný odkaz na prehliadanie logu: https://VAŠA-DOMÉNA/match-day/log/<LOG_SECRET>
// Nikomu ho neposielajte. Zmenou hodnoty starý odkaz prestane fungovať; '' = vypnuté.
defined('LOG_SECRET') || define('LOG_SECRET', '');                 // nastavte v config.local.php

// ---------------------------------------------------------------------------------
// EXTERNÉ API
// ---------------------------------------------------------------------------------
define('API_SEASONS', 'https://sutaze.api.sportnet.online/api/v2/public/futbalsfz.sk/seasons');
define('API_TEAMS', 'https://sutaze.sportnet.online/api/v2/public/%s.futbalnet.sk/teams?limit=100&offset=0&seasonName=%s');
define('API_MATCHES', 'https://sutaze.api.sportnet.online/api/v2/futbalnet/matches?teamId=%s&sorter=dateFromAsc&offset=0&limit=100');
define('API_MATCH', 'https://sutaze.sportnet.online/api/v2/matches/%s?noCache=0');

// ---------------------------------------------------------------------------------
// ROUTES
// ---------------------------------------------------------------------------------
$routes = array(
    ""                        => "index",
    "match-day-season-detail" => "season-detail",
    "generator"               => "generator",
    "nastavenia"              => "settings",
    "galeria"                 => "gallery",

    // PWA
    "manifest.webmanifest"    => "pwa/manifest",
    "sw.js"                   => "pwa/sw",
    "offline"                 => "offline",
    "log"                     => "log-viewer",

    // JSON / obrázkové API pre prehliadač
    "api-logo"                => "api/logo",
    "api-match"               => "api/match",
    "api-save"                => "api/save",
    "api-gallery"             => "api/gallery",
    "api-log"                 => "api/log",

    // pôvodné serverové generátory (GD) – ponechané ako záloha
    "generate-match-result"   => "generate/generate-result",
    "generate-match-goals"    => "generate/generate-goals",
    "generate-match-day"      => "generate/generate",
);
