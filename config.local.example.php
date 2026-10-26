<?php
/**
 * Lokálne nastavenia servera – skopírujte tento súbor ako config.local.php
 * a vyplňte vlastné hodnoty. Súbor config.local.php sa do gitu NENAHRÁVA.
 */

// Heslo pre galériu všetkých prehliadačov: /match-day/galeria?admin=HESLO   ('' = vypnuté)
define('GALLERY_ADMIN_KEY', '');

// Tajný kľúč pre prehliadanie logu: /match-day/log/KĽÚČ   ('' = vypnuté)
// Vygenerujte náhodný reťazec, napr.: php -r "echo bin2hex(random_bytes(16));"
define('LOG_SECRET', '');

// Zapisovanie logu generovania (true / false)
define('LOG_ENABLED', true);
