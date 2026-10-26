<?php
/**
 * Lokálne nastavenia servera – skopírujte tento súbor ako config.local.php
 * a vyplňte vlastné hodnoty. Súbor config.local.php sa do gitu NENAHRÁVA.
 */

// Heslo správcu ('' = vypnuté). Prihlásenie na adrese /match-day/spravca –
// správca vidí galériu všetkých prehliadačov, môže mazať obrázky a prezerať log generovania.
// Vygenerujte dlhé náhodné heslo, napr.: php -r "echo bin2hex(random_bytes(12));"
define('GALLERY_ADMIN_KEY', '');

// Zapisovanie logu generovania (true / false)
define('LOG_ENABLED', true);

// Vývojový režim – zobrazuje chyby PHP priamo na stránke. Na produkcii false.
define('APP_DEBUG', false);
