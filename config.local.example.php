<?php
// Väčšinu nastavení upravíte priamo v aplikácii: Správca → Nastavenia (ukladajú sa do storage/private/settings.json).
// Čo zadáte tu, má prednosť a v aplikácii je uzamknuté – sem patrí hlavne heslo správcu.
/**
 * Lokálne nastavenia servera – skopírujte tento súbor ako config.local.php
 * a vyplňte vlastné hodnoty. Súbor config.local.php sa do gitu NENAHRÁVA.
 */

// Heslo správcu (predvolené asd123, '' = vypnuté). Prihlásenie na adrese /match-day/spravca –
// správca vidí galériu všetkých prehliadačov, môže mazať obrázky a prezerať log generovania.
// Na verejnom serveri predvolené heslo zmeňte, napr.: php -r "echo bin2hex(random_bytes(12));"
define('GALLERY_ADMIN_KEY', 'asd123');

// Zapisovanie logu generovania (true / false)
// define('LOG_ENABLED', true);

// Vývojový režim – zobrazuje chyby PHP priamo na stránke. Na produkcii false.
// define('APP_DEBUG', false);

// Heslo správcu môže byť aj hash (odporúčané), napr.:
//   php -r "echo password_hash('moje-heslo', PASSWORD_DEFAULT);"
// define('GALLERY_ADMIN_KEY', '$2y$10$....');
// Heslo sa dá zmeniť aj priamo v aplikácii (Správca → Zmeniť heslo) – uloží sa iba jeho hash.

// E-mail pre upozornenia z nočnej kontroly Sportnetu a kľúč pre spustenie cez URL
// define('ADMIN_EMAIL', 'vas@email.sk');
// define('CRON_KEY', 'dlhy-nahodny-retazec');

// Ak hosting nemá aktuálne CA certifikáty (chyba „SSL certificate problem“):
// define('SSL_CAINFO', __DIR__ . '/storage/private/cacert.pem');

// Ďalšie domény lôg a fotiek (oddelené čiarkou), limity a uchovávanie obrázkov
// define('IMAGE_HOSTS', 'sportnet.online,futbalnet.sk,mediamanager.ws,moja-domena.sk');
// define('STORAGE_MAX_MB', 3000);
// define('STORAGE_KEEP_DAYS', 365);

// Odosielanie e-mailov cez SMTP (Správca → Poslať skúšobný e-mail overí nastavenie)
// define('SMTP_HOST', 'smtp.websupport.sk');   // Gmail: smtp.gmail.com (heslo aplikácie), Seznam: smtp.seznam.cz
// define('SMTP_PORT', 465);                     // 465 = SSL, 587 = STARTTLS
// define('SMTP_USER', 'noreply@vas-web.sk');
// define('SMTP_PASS', 'heslo-schranky');
// define('MAIL_FROM', 'noreply@vas-web.sk');    // pri Gmaile musí byť rovnaká ako SMTP_USER
