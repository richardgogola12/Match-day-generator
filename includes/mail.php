<?php
/**
 * Odosielanie e-mailov – cez SMTP (ak je nastavený SMTP_HOST), inak funkciou PHP mail().
 * Bez externých knižníc: SSL (port 465) aj STARTTLS (port 587), prihlásenie AUTH LOGIN / PLAIN.
 *
 * config.local.php:
 *   define('SMTP_HOST', 'smtp.vas-hosting.sk');
 *   define('SMTP_PORT', 587);              // 587 = STARTTLS, 465 = SSL, 25 = bez šifrovania
 *   define('SMTP_SECURE', 'tls');          // 'tls' (STARTTLS), 'ssl', alebo '' (bez šifrovania)
 *   define('SMTP_USER', 'noreply@vas-web.sk');
 *   define('SMTP_PASS', 'heslo');
 *   define('MAIL_FROM', 'noreply@vas-web.sk');
 */

defined('SMTP_HOST') || define('SMTP_HOST', '');
defined('SMTP_PORT') || define('SMTP_PORT', 587);
defined('SMTP_SECURE') || define('SMTP_SECURE', (int)SMTP_PORT === 465 ? 'ssl' : ((int)SMTP_PORT === 25 ? '' : 'tls'));
defined('SMTP_USER') || define('SMTP_USER', '');
defined('SMTP_PASS') || define('SMTP_PASS', '');
defined('SMTP_TIMEOUT') || define('SMTP_TIMEOUT', 20);

/** adresa odosielateľa */
function md_mail_from() {
    if (MAIL_FROM !== '') return MAIL_FROM;
    if (SMTP_USER !== '' && str_contains(SMTP_USER, '@')) return SMTP_USER;
    $host = parse_url(defined('APP_URL') ? APP_URL : '', PHP_URL_HOST) ?: 'localhost';
    return 'noreply@' . preg_replace('/^www\./', '', $host);
}

/**
 * Pošle textový e-mail.
 * @return array [ok (bool), správa (čo sa stalo / chyba)]
 */
function md_send_mail($to, $subject, $body) {
    $to = trim((string)$to);
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) return [false, 'Neplatná e-mailová adresa príjemcu.'];
    $from = md_mail_from();
    if (!filter_var($from, FILTER_VALIDATE_EMAIL)) return [false, 'Neplatná adresa odosielateľa (MAIL_FROM).'];
    $subj = '=?UTF-8?B?' . base64_encode($subject) . '?=';
    $body = str_replace(["\r\n", "\r"], "\n", (string)$body);

    if (SMTP_HOST === '') {
        $ok = @mail($to, $subj, $body, "From: Match Day <$from>\r\nMIME-Version: 1.0\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: 8bit");
        return [$ok, $ok ? 'Odoslané funkciou mail() servera.' : 'Funkcia mail() na serveri zlyhala – nastavte SMTP v config.local.php.'];
    }
    try {
        md_smtp_send($from, $to, $subj, $body);
        return [true, 'Odoslané cez SMTP ' . SMTP_HOST . ':' . (int)SMTP_PORT . '.'];
    } catch (Throwable $e) {
        return [false, 'SMTP: ' . $e->getMessage()];
    }
}

function md_smtp_send($from, $to, $subj, $body) {
    $host = SMTP_HOST; $port = (int)SMTP_PORT; $secure = strtolower((string)SMTP_SECURE);
    $ctx = stream_context_create(['ssl' => array_filter([
        'verify_peer' => true, 'verify_peer_name' => true, 'peer_name' => $host,
        'cafile' => (SSL_CAINFO !== '' && is_file(SSL_CAINFO)) ? SSL_CAINFO : null,
    ], fn($v) => $v !== null)]);
    $remote = ($secure === 'ssl' ? 'ssl://' : 'tcp://') . $host . ':' . $port;
    $fp = @stream_socket_client($remote, $errno, $errstr, SMTP_TIMEOUT, STREAM_CLIENT_CONNECT, $ctx);
    if (!$fp && $secure === 'ssl' && is_file(__DIR__ . '/cacert.pem')) { // hosting bez aktuálnych CA
        stream_context_set_option($ctx, 'ssl', 'cafile', __DIR__ . '/cacert.pem');
        $fp = @stream_socket_client($remote, $errno, $errstr, SMTP_TIMEOUT, STREAM_CLIENT_CONNECT, $ctx);
    }
    if (!$fp) throw new RuntimeException('pripojenie na ' . $host . ':' . $port . ' zlyhalo (' . trim($errstr ?: 'chyba ' . $errno) . ')');
    stream_set_timeout($fp, SMTP_TIMEOUT);

    $read = function () use ($fp) {
        $data = '';
        while (($line = fgets($fp, 1024)) !== false) {
            $data .= $line;
            if (strlen($line) < 4 || $line[3] === ' ') break; // posledný riadok odpovede
        }
        if ($data === '') throw new RuntimeException('server neodpovedá');
        return [(int)substr($data, 0, 3), trim($data)];
    };
    $cmd = function ($line, $expect, $hide = false) use ($fp, $read) {
        fwrite($fp, $line . "\r\n");
        [$code, $text] = $read();
        if (!in_array($code, (array)$expect, true)) throw new RuntimeException(($hide ? 'prihlásenie zlyhalo, skontrolujte SMTP_USER a SMTP_PASS' : strtok($line, ' ')) . ' – ' . $text);
        return $text;
    };
    try {
        [$code, $text] = $read();
        if ($code !== 220) throw new RuntimeException('server: ' . $text);
        $me = parse_url(defined('APP_URL') ? APP_URL : '', PHP_URL_HOST) ?: 'localhost';
        $ehlo = $cmd('EHLO ' . $me, 250);
        if ($secure === 'tls') {
            if (stripos($ehlo, 'STARTTLS') === false) throw new RuntimeException('server nepodporuje STARTTLS – skúste SMTP_PORT 465 a SMTP_SECURE \'ssl\'');
            $cmd('STARTTLS', 220);
            $crypto = STREAM_CRYPTO_METHOD_TLS_CLIENT;
            if (defined('STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT')) $crypto |= STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT;
            if (defined('STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT')) $crypto |= STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT;
            $ok = @stream_socket_enable_crypto($fp, true, $crypto);
            if (!$ok && is_file(__DIR__ . '/cacert.pem')) {
                stream_context_set_option($fp, 'ssl', 'cafile', __DIR__ . '/cacert.pem');
                $ok = @stream_socket_enable_crypto($fp, true, $crypto);
            }
            if (!$ok) throw new RuntimeException('šifrované spojenie (STARTTLS) sa nepodarilo – overte certifikát servera');
            $ehlo = $cmd('EHLO ' . $me, 250);
        }
        if (SMTP_USER !== '') {
            if (preg_match('/AUTH[ =][^\r\n]*\bLOGIN\b/i', $ehlo) || !preg_match('/AUTH[ =][^\r\n]*\bPLAIN\b/i', $ehlo)) {
                $cmd('AUTH LOGIN', 334);
                $cmd(base64_encode(SMTP_USER), 334, true);
                $cmd(base64_encode(SMTP_PASS), 235, true);
            } else {
                $cmd('AUTH PLAIN ' . base64_encode("\0" . SMTP_USER . "\0" . SMTP_PASS), 235, true);
            }
        }
        $cmd('MAIL FROM:<' . $from . '>', 250);
        $cmd('RCPT TO:<' . $to . '>', [250, 251]);
        $cmd('DATA', 354);
        $domain = substr(strrchr($from, '@'), 1) ?: 'localhost';
        $headers = [
            'Date: ' . date('r'),
            'From: Match Day <' . $from . '>',
            'To: <' . $to . '>',
            'Subject: ' . $subj,
            'Message-ID: <' . bin2hex(random_bytes(12)) . '@' . $domain . '>',
            'MIME-Version: 1.0',
            'Content-Type: text/plain; charset=UTF-8',
            'Content-Transfer-Encoding: base64',
            'Auto-Submitted: auto-generated',
        ];
        $msg = implode("\r\n", $headers) . "\r\n\r\n" . rtrim(chunk_split(base64_encode($body), 76, "\r\n"));
        $cmd($msg . "\r\n.", 250);
        @fwrite($fp, "QUIT\r\n");
    } finally {
        @fclose($fp);
    }
}
