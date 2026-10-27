<?php
/**
 * Nočná úloha cez URL (webcron hostingu): /cron?key=<CRON_KEY>
 * Bez nastaveného CRON_KEY je vypnutá – použite php cli/cron.php.
 */
no_store_headers();
$key = (string)($_GET['key'] ?? '');
if (CRON_KEY === '' || !hash_equals(CRON_KEY, $key)) { http_response_code(403); header('Content-Type: text/plain; charset=utf-8'); exit("Zakázané.\n"); }
if (!md_rate_limit('cron', 6, 3600)) { http_response_code(429); exit("Príliš často.\n"); }
require_once INCLUDES_DIR . 'cron.php';
while (ob_get_level() > 0) ob_end_clean();
header('Content-Type: text/plain; charset=utf-8');
md_cron(function ($m) { echo $m, "\n"; @flush(); });
exit;
