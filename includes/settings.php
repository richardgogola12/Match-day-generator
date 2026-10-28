<?php
/**
 * Nastavenia upraviteľné v aplikácii (Správca → Nastavenia).
 * Ukladajú sa do storage/private/settings.json (z webu nedostupné) a načítajú sa
 * hneď po config.local.php. Hodnota v config.local.php má vždy prednosť – v správe je potom uzamknutá.
 */

/** popis všetkých nastavení: kľúč => [typ, predvolená hodnota, názov, pomoc, skupina, voľby] */
function md_settings_schema() {
    return [
        // Všeobecné
        'APP_NAME'             => ['text', 'Match Day Generator', 'Názov aplikácie', 'Zobrazuje sa v titulku stránok, e-mailoch a metadátach obrázkov.', 'general'],
        'APP_TIMEZONE'         => ['select', 'Europe/Bratislava', 'Časové pásmo', 'Dátumy v galérii, logoch a údržbe.', 'general', ['Europe/Bratislava' => 'Bratislava', 'Europe/Prague' => 'Praha', 'Europe/Budapest' => 'Budapešť', 'Europe/Vienna' => 'Viedeň', 'UTC' => 'UTC']],
        'APP_DEBUG'            => ['bool', false, 'Vývojový režim', 'Zobrazuje chyby PHP priamo na stránke. Na verejnom serveri nechajte vypnuté.', 'general'],
        'LOG_ENABLED'          => ['bool', true, 'Log generovania', 'Zapisovať, kedy a aký obrázok vznikol (bez osobných údajov).', 'general'],
        'LOG_RETENTION_MONTHS' => ['int', 12, 'Uchovávať logy (mesiace)', 'Staršie mesačné logy sa automaticky zmažú. 0 = nikdy.', 'general', [0, 120]],
        // E-mail
        'ADMIN_EMAIL'          => ['email', '', 'E-mail správcu', 'Sem príde upozornenie, keď nočná kontrola nájde chybu Sportnetu.', 'mail'],
        'MAIL_FROM'            => ['email', '', 'Odosielateľ', 'Prázdne = účet SMTP. Pri Gmaile musí byť rovnaký ako účet.', 'mail'],
        'SMTP_HOST'            => ['text', '', 'SMTP server', 'Napr. smtp.websupport.sk, smtp.gmail.com, smtp.seznam.cz. Prázdne = funkcia mail() servera.', 'mail'],
        'SMTP_PORT'            => ['int', 587, 'Port', '465 = SSL, 587 = STARTTLS, 25 = bez šifrovania.', 'mail', [1, 65535]],
        'SMTP_SECURE'          => ['select', 'auto', 'Šifrovanie', 'Automaticky podľa portu.', 'mail', ['auto' => 'Automaticky podľa portu', 'ssl' => 'SSL', 'tls' => 'STARTTLS', 'none' => 'Bez šifrovania']],
        'SMTP_USER'            => ['text', '', 'Používateľ (e-mail schránky)', '', 'mail'],
        'SMTP_PASS'            => ['secret', '', 'Heslo schránky', 'Pri Gmaile heslo aplikácie. Nechajte prázdne, ak ho nechcete meniť.', 'mail'],
        // Úložisko a limity
        'STORAGE_MAX_MB'       => ['int', 3000, 'Max. veľkosť úložiska (MB)', 'Spolu pre všetky uložené obrázky. 0 = bez limitu.', 'storage', [0, 1000000]],
        'STORAGE_KEEP_DAYS'    => ['int', 365, 'Mazať obrázky staršie ako (dni)', '0 = nikdy nemazať.', 'storage', [0, 36500]],
        'SAVE_LIMIT_CLIENT'    => ['int', 180, 'Uložení za hodinu z prehliadača', 'Ochrana pred zaplnením disku. 0 = bez limitu.', 'storage', [0, 100000]],
        'SAVE_LIMIT_IP'        => ['int', 240, 'Uložení za hodinu z IP adresy', '0 = bez limitu.', 'storage', [0, 100000]],
        // Sportnet a bezpečnosť
        'IMAGE_HOSTS'          => ['list', 'sportnet.online,futbalnet.sk,futbalsfz.sk,sportnet.sk,sme.sk,mediamanager.ws,issf.sk', 'Povolené domény lôg a fotiek', 'Jedna doména na riadok (platí aj pre subdomény). Domény z odpovedí Sportnetu sa pridávajú automaticky.', 'security'],
        'SSL_VERIFY'           => ['bool', true, 'Overovať HTTPS certifikáty', 'Vypnite iba v núdzi, ak hosting nevie overiť certifikát Sportnetu.', 'security'],
        'SSL_CAINFO'           => ['text', '', 'Vlastný súbor CA certifikátov', 'Cesta na serveri k cacert.pem. Prázdne = systémové + priložený balík.', 'security'],
        // Nočná úloha
        'CRON_KEY'             => ['secret', '', 'Kľúč pre webcron', 'Povolí spustenie cez adresu /cron?key=… Prázdne = iba z príkazového riadku (php cli/cron.php).', 'cron'],
        'CRON_CHECK_CLUB'      => ['text', 'nitra', 'Klub na nočnú kontrolu Sportnetu', 'Na ňom sa každú noc overia všetky zdroje dát.', 'cron'],
    ];
}

function md_settings_groups() {
    return [
        'general'  => ['bi-gear', 'Všeobecné'],
        'mail'     => ['bi-envelope', 'E-mail (SMTP)'],
        'storage'  => ['bi-hdd', 'Úložisko a limity'],
        'security' => ['bi-shield-lock', 'Sportnet a bezpečnosť'],
        'cron'     => ['bi-moon-stars', 'Nočná úloha'],
    ];
}

function md_settings_file() {
    return dirname(__DIR__) . '/storage/private/settings.json';
}

function md_settings_saved() {
    $f = md_settings_file();
    $d = is_file($f) ? json_decode((string)@file_get_contents($f), true) : null;
    return is_array($d) ? $d : [];
}

/** prevedie uloženú hodnotu na hodnotu konštanty */
function md_settings_value($key, $raw, array $def) {
    switch ($def[0]) {
        case 'bool': return (bool)$raw;
        case 'int': return (int)$raw;
        case 'list': return is_array($raw) ? implode(',', $raw) : (string)$raw;
        case 'select':
            if ($key === 'SMTP_SECURE') return $raw === 'none' ? '' : ($raw === 'auto' ? null : (string)$raw);
            return (string)$raw;
        default: return (string)$raw;
    }
}

/**
 * Načíta uložené nastavenia ako konštanty (volá config.php hneď po config.local.php).
 * Kľúče definované v config.local.php sa preskočia a označia ako uzamknuté.
 */
function md_settings_apply() {
    $GLOBALS['md_settings_locked'] = [];
    $saved = md_settings_saved();
    foreach (md_settings_schema() as $key => $def) {
        if (defined($key)) { $GLOBALS['md_settings_locked'][$key] = true; continue; }
        if (!array_key_exists($key, $saved)) continue;
        $v = md_settings_value($key, $saved[$key], $def);
        if ($v !== null) define($key, $v);
    }
}

function md_settings_locked($key) {
    return !empty($GLOBALS['md_settings_locked'][$key]);
}

/**
 * Overí a uloží formulár. Vracia [ok, chyby[]].
 */
function md_settings_save(array $post) {
    $schema = md_settings_schema();
    $saved = md_settings_saved();
    $errors = [];
    foreach ($schema as $key => $def) {
        if (md_settings_locked($key)) continue;
        [$type, $default] = $def;
        $label = $def[2];
        if ($type === 'bool') { $saved[$key] = !empty($post[$key]); continue; }
        $val = trim((string)($post[$key] ?? ''));
        if ($type === 'secret') {
            if (!empty($post[$key . '__clear'])) { $saved[$key] = ''; continue; }
            if ($val === '') continue; // prázdne = nemeniť
            if (strlen($val) > 300) { $errors[$key] = $label . ': príliš dlhé.'; continue; }
            $saved[$key] = $val; continue;
        }
        if ($val === '') { unset($saved[$key]); continue; } // prázdne = predvolené
        switch ($type) {
            case 'int':
                [$min, $max] = $def[5] ?? [PHP_INT_MIN, PHP_INT_MAX];
                if (!preg_match('/^-?\d+$/', $val) || (int)$val < $min || (int)$val > $max) { $errors[$key] = $label . ': zadajte celé číslo ' . $min . ' – ' . $max . '.'; continue 2; }
                $saved[$key] = (int)$val; break;
            case 'email':
                if (!filter_var($val, FILTER_VALIDATE_EMAIL)) { $errors[$key] = $label . ': neplatná e-mailová adresa.'; continue 2; }
                $saved[$key] = $val; break;
            case 'select':
                if (!array_key_exists($val, $def[5])) { $errors[$key] = $label . ': neplatná voľba.'; continue 2; }
                $saved[$key] = $val; break;
            case 'list':
                $hosts = array_values(array_unique(array_filter(array_map(fn($h) => strtolower(trim($h, " \t.")), preg_split('/[\s,;]+/', $val)))));
                foreach ($hosts as $h) if (!preg_match('/^[a-z0-9]([a-z0-9-]*[a-z0-9])?(\.[a-z0-9]([a-z0-9-]*[a-z0-9])?)+$|^127\.0\.0\.1$/', $h)) { $errors[$key] = $label . ': neplatná doména „' . $h . '“.'; continue 3; }
                $saved[$key] = implode(',', $hosts); break;
            default:
                if (mb_strlen($val) > 300 || preg_match('/[\x00-\x1f]/', $val)) { $errors[$key] = $label . ': neplatná hodnota.'; continue 2; }
                if ($key === 'SMTP_HOST' && !preg_match('/^[a-z0-9.\-]+$/i', $val)) { $errors[$key] = $label . ': zadajte iba názov servera (bez https:// a portu).'; continue 2; }
                if ($key === 'SSL_CAINFO' && !is_file($val)) { $errors[$key] = $label . ': súbor na serveri neexistuje.'; continue 2; }
                $saved[$key] = $val;
        }
    }
    if ($errors) return [false, $errors];
    $f = md_settings_file();
    if (!is_dir(dirname($f))) @mkdir(dirname($f), 0775, true);
    $saved['_updated'] = date('c');
    $tmp = $f . '.tmp' . getmypid();
    $ok = @file_put_contents($tmp, json_encode($saved, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), LOCK_EX) !== false && @rename($tmp, $f);
    if ($ok) @chmod($f, 0640);
    return $ok ? [true, []] : [false, ['_file' => 'Nastavenia sa nepodarilo uložiť – priečinok storage/private nie je zapisovateľný.']];
}
