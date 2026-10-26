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

// Vývojový režim: zobrazuje chyby PHP na stránke. Na produkcii nechajte false (nastavte v config.local.php).
defined('APP_DEBUG') || define('APP_DEBUG', false);

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
// Heslo správcu: po prihlásení na adrese /spravca uvidíte obrázky zo všetkých prehliadačov a log.
// Heslo sa zadáva iba do formulára (nikdy nie do adresy), preto sa nedostane do logov servera.
// Predvolené heslo je asd123 – na verejnom serveri ho zmeňte v config.local.php.
defined('GALLERY_ADMIN_KEY') || define('GALLERY_ADMIN_KEY', 'asd123');

// ---------------------------------------------------------------------------------
// LOG GENEROVANIA (storage/logs/generovanie-RRRR-MM.txt, z webu nedostupné)
// Zapisuje sa iba čo, kedy a aký typ zariadenia – žiadne IP adresy ani údaje o zariadení.
// Prehliadanie: /log (iba prihlásený správca).
// ---------------------------------------------------------------------------------
defined('LOG_ENABLED') || define('LOG_ENABLED', true);
define('LOG_DIR', APP_DIR . '/storage/logs/');
defined('LOG_RETENTION_MONTHS') || define('LOG_RETENTION_MONTHS', 12); // staršie mesačné logy sa automaticky zmažú (0 = nikdy)

// ---------------------------------------------------------------------------------
// EXTERNÉ API
// ---------------------------------------------------------------------------------
define('API_SEASONS', 'https://sutaze.api.sportnet.online/api/v2/public/futbalsfz.sk/seasons');
define('API_TEAMS', 'https://sutaze.sportnet.online/api/v2/public/%s.futbalnet.sk/teams?limit=100&offset=0&seasonName=%s');
define('API_MATCHES', 'https://sutaze.api.sportnet.online/api/v2/futbalnet/matches?teamId=%s&sorter=dateFromAsc&offset=0&limit=100');
define('API_MATCH', 'https://sutaze.sportnet.online/api/v2/matches/%s?noCache=0');
// Sportnet Sútaže API v2 (dokumentácia: https://sutaze.api.sportnet.online/api/v2/docs/)
define('API_BASE', 'https://sutaze.sportnet.online/api/v2');
define('API_CLUB_TEAMS', API_BASE . '/public/%s/teams?limit=100&offset=0&seasonName=%s');       // družstvá klubu (aj issf_club_…)
define('API_PART_TABLE', API_BASE . '/public/competitions/%s/parts/%s/results');                // oficiálna tabuľka časti súťaže
define('API_COMP_PLAYERS', API_BASE . '/public/competitions/%s/players?%s');                    // štatistiky hráčov (minúty, štarty, striedania)
define('API_PLAYER_STATS', API_BASE . '/player/%s/statistics');                                 // kariérne štatistiky hráča
define('API_H2H', API_BASE . '/futbalnet/h2h-matches?teamAppSpaces=%s&teamAppSpaces=%s&limit=40');   // vzájomné zápasy klubov
define('API_PLAYER_MATCHES', API_BASE . '/player-matches/ppo/%s/player/%s?limit=%d&closed=true&sorter=dateFromDesc');
define('API_USER', 'https://api.sportnet.online/v1/ppo/futbalsfz.sk/users/%s');                     // hráč: meno, fotka, rok narodenia
define('API_USER_CLUBS', 'https://api.sportnet.online/v1/users/%s');                                // kluby hráča
define('API_PLAYER_SEARCH', 'https://api.sportnet.online/v1/ppo/futbalsfz.sk/users?q=%s&limit=%d');  // hľadanie hráčov
define('API_CLUB_SEARCH', 'https://api.sportnet.online/v1/ppo/futbalsfz.sk/related-ppos?ppoType=club&limit=%d&offset=0&q=%s');

// ---------------------------------------------------------------------------------
// ROUTES
// ---------------------------------------------------------------------------------
$routes = array(
    ""                        => "index",
    "match-day-season-detail" => "season-detail",
    "generator"               => "generator",
    "nastavenia"              => "settings",
    "galeria"                 => "gallery",
    "spravca"                 => "admin",
    "navod"                   => "navod",
    "druzstvo"                => "team",
    "hrac"                    => "player",
    "zapas"                   => "match",

    // PWA
    "manifest.webmanifest"    => "pwa/manifest",
    "sw.js"                   => "pwa/sw",
    "offline"                 => "offline",
    "log"                     => "log-viewer",

    // JSON / obrázkové API pre prehliadač
    "api-logo"                => "api/logo",
    "api-match"               => "api/match",
    "api-squad"               => "api/squad",
    "api-team"                => "api/team",
    "api-save"                => "api/save",
    "api-gallery"             => "api/gallery",
    "api-search"              => "api/search",
    "api-h2h"                 => "api/h2h",
    "api-player"              => "api/player",

    // pôvodné serverové generátory (GD) – ponechané ako záloha
    "generate-match-result"   => "generate/generate-result",
    "generate-match-goals"    => "generate/generate-goals",
    "generate-match-day"      => "generate/generate",
);
