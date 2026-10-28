<?php
/**
 * Nočná úloha: údržba úložiska, obnova cache Sportnetu pre používané družstvá a kontrola Sportnetu
 * (pri chybe e-mail na ADMIN_EMAIL). Spúšťa sa z cronu:  php cli/cron.php
 * alebo cez URL /cron?key=<CRON_KEY> (napr. z webcron služby hostingu).
 */
require_once __DIR__ . '/check.php';

function md_cron($log = null) {
    $log = $log ?: function ($m) {};
    @set_time_limit(600);
    ignore_user_abort(true);
    $GLOBALS['md_in_bg'] = true; // všetko sa sťahuje nanovo (bez vracania starej cache)
    $t0 = microtime(true);
    $state = ['at' => date('c')];

    // 1. údržba
    $m = md_maintenance(true);
    $log('Údržba: zmazaných ' . $m['images'] . ' starých obrázkov, ' . $m['cache'] . ' súborov cache.');
    $state['maintenance'] = $m;

    // 2. obnova cache: družstvá a kluby z obrázkov za posledné 3 týždne
    $teams = []; $clubs = [];
    $since = time() - 21 * 86400;
    foreach (glob(STORAGE_DIR . '*/*.json') ?: [] as $jf) {
        if (filemtime($jf) < $since) continue;
        $meta = md_json_read($jf);
        $id = (string)($meta['teamID'] ?? '');
        if ($id === '' || !preg_match('/^[A-Za-z0-9_\-.]{2,120}$/', $id)) continue;
        if (($meta['type'] ?? '') === 'weekend') $clubs[$id] = true; else $teams[$id] = true;
    }
    $teams = array_slice(array_keys($teams), 0, 40);
    foreach ($teams as $id) { sportnet_competition($id); sportnet_team($id); }
    foreach (array_slice(array_keys($clubs), 0, 15) as $c) {
        $appSpace = str_starts_with($c, 'issf_') || str_contains($c, '.') ? $c : $c . '.futbalnet.sk';
        $res = sn_fetch_cached(sprintf(API_CLUB_TEAMS, rawurlencode($appSpace), rawurlencode(md_default_season())), 0);
        $ids = array_map(fn($t) => (string)$t['_id'], array_values((array)($res['teams'] ?? [])));
        if ($ids) sn_fetch_many(array_map(fn($i) => sprintf(API_MATCHES, rawurlencode($i)), $ids), 0);
    }
    $log('Cache obnovená: ' . count($teams) . ' družstiev, ' . count($clubs) . ' klubov.');
    $state['warmed'] = ['teams' => count($teams), 'clubs' => count($clubs)];

    // 3. kontrola Sportnetu
    $checks = md_sportnet_checks(defined('CRON_CHECK_CLUB') ? CRON_CHECK_CLUB : 'nitra');
    $fails = array_values(array_filter($checks, fn($c) => $c['state'] === 'fail'));
    $state['problems'] = count($fails);
    $state['checks'] = count($checks);
    $log('Kontrola Sportnetu: ' . count($checks) . ' zdrojov, ' . count($fails) . ' chýb.');
    foreach ($fails as $f) $log('  CHYBA ' . $f['name'] . ': ' . $f['msg']);

    // 4. e-mail pri chybe (najviac raz za 20 hodín)
    $prev = md_json_read(md_private_file('cron.json'));
    $state['mailed'] = $prev['mailed'] ?? 0;
    if ($fails && ADMIN_EMAIL !== '' && ($prev['mailed'] ?? 0) < time() - 20 * 3600) {
        $body = "Nočná kontrola Sportnetu našla " . count($fails) . " chýb:\n\n";
        foreach ($fails as $f) $body .= "• " . $f['name'] . " (" . $f['what'] . "): " . $f['msg'] . "\n  " . $f['url'] . "\n";
        $body .= "\nPodrobnosti: " . APP_URL . "/kontrola\n";
        [$ok, $info] = md_send_mail(ADMIN_EMAIL, 'Match Day: problém so Sportnetom', $body);
        if ($ok) $state['mailed'] = time();
        $state['mail'] = $info;
        $log(($ok ? 'E-mail odoslaný na ' . ADMIN_EMAIL . '. ' : 'E-mail sa nepodarilo odoslať. ') . $info);
    }
    $state['seconds'] = round(microtime(true) - $t0, 1);
    md_json_write(md_private_file('cron.json'), $state);
    $log('Hotovo za ' . $state['seconds'] . ' s.');
    return $state;
}
