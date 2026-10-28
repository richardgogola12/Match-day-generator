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
// časové pásmo (dátumy v logoch, galérii a údržbe)
date_default_timezone_set(defined('APP_TIMEZONE') ? APP_TIMEZONE : 'Europe/Bratislava');

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
// CACHE, BEZPEČNOSŤ A ÚDRŽBA (všetko sa dá zmeniť v config.local.php)
// ---------------------------------------------------------------------------------
define('CACHE_DIR', APP_DIR . '/storage/cache');                 // cache Sportnetu a lôg (z webu nedostupná)
define('LOGO_CACHE_DIR', CACHE_DIR . '/logos');
define('PRIVATE_DIR', APP_DIR . '/storage/private');             // heslo správcu (hash), tajný kľúč, stav údržby
// Overovanie HTTPS certifikátov pri spojení so Sportnetom. Ak hosting nemá aktuálne CA certifikáty,
// nastavte SSL_CAINFO na cestu k súboru cacert.pem (https://curl.se/ca/cacert.pem); vypnúť iba v núdzi.
defined('SSL_VERIFY') || define('SSL_VERIFY', true);
defined('SSL_CAINFO') || define('SSL_CAINFO', '');
// Domény, z ktorých server smie sťahovať logá a fotky (+ domény, ktoré sa objavia v odpovediach Sportnetu).
defined('IMAGE_HOSTS') || define('IMAGE_HOSTS', 'sportnet.online,futbalnet.sk,futbalsfz.sk,sportnet.sk,sme.sk,mediamanager.ws,issf.sk');
// Limity ukladania obrázkov na server
defined('SAVE_LIMIT_IP') || define('SAVE_LIMIT_IP', 240);          // uložení za hodinu z jednej IP adresy
defined('SAVE_LIMIT_CLIENT') || define('SAVE_LIMIT_CLIENT', 180);  // uložení za hodinu z jedného prehliadača
defined('STORAGE_MAX_MB') || define('STORAGE_MAX_MB', 3000);        // max. veľkosť všetkých uložených obrázkov (0 = bez limitu)
defined('STORAGE_KEEP_DAYS') || define('STORAGE_KEEP_DAYS', 365);   // obrázky staršie ako N dní sa automaticky zmažú (0 = nikdy)
// Nočná úloha (cron): obnova cache, údržba a kontrola Sportnetu – viď README
defined('CRON_KEY') || define('CRON_KEY', '');                     // kľúč pre spustenie cez URL /cron?key=… ('' = iba z príkazového riadku)
defined('ADMIN_EMAIL') || define('ADMIN_EMAIL', '');               // sem príde e-mail, keď kontrola Sportnetu nájde chybu
defined('MAIL_FROM') || define('MAIL_FROM', '');                   // odosielateľ e-mailu (prázdne = SMTP_USER alebo noreply@doména)
// SMTP (odporúčané – e-maily z mail() často končia v spame). Prázdny SMTP_HOST = funkcia mail() servera.
defined('SMTP_HOST') || define('SMTP_HOST', '');                   // napr. smtp.websupport.sk, smtp.gmail.com
defined('SMTP_PORT') || define('SMTP_PORT', 587);                  // 587 = STARTTLS, 465 = SSL
defined('SMTP_SECURE') || define('SMTP_SECURE', (int)SMTP_PORT === 465 ? 'ssl' : ((int)SMTP_PORT === 25 ? '' : 'tls'));
defined('SMTP_USER') || define('SMTP_USER', '');
defined('SMTP_PASS') || define('SMTP_PASS', '');

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
    "kontrola"                => "sportnet-check",
    "zdielaj"                 => "share",
    "znacka"                  => "brand",
    "cron"                    => "cron",

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
    "api-club"                => "api/club",
    "api-create"              => "api/create",
    "api-photos"              => "api/photos",

    // pôvodné serverové generátory (GD) – ponechané ako záloha
    "generate-match-result"   => "generate/generate-result",
    "generate-match-goals"    => "generate/generate-goals",
    "generate-match-day"      => "generate/generate",
);
