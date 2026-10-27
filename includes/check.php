<?php
/**
 * Kontrola zdrojov dát Sportnetu – používa ju stránka /kontrola aj nočná úloha (cron).
 */

/** Jedna požiadavka bez cache: [http kód, ms, JSON|null] */
function md_probe($url) {
    $t = microtime(true);
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true, CURLOPT_TIMEOUT => 20,
        CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120 Safari/537.36',
        CURLOPT_HTTPHEADER => ['Accept: application/json']] + md_curl_ssl_opts(true));
    $body = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);
    $json = is_string($body) ? json_decode($body, true) : null;
    return [$code ?: ($err ? 0 : 0), (int)round((microtime(true) - $t) * 1000), is_array($json) ? $json : null, $err];
}


/**
 * Spustí všetky kontroly na klube podľa hľadaného textu.
 * @return array zoznam kontrol: name, what, state (ok|warn|fail|skip), msg, code, ms, url
 */
function md_sportnet_checks($q) {
$checks = [];
/** Zaznamená výsledok kontroly; $test vráti [ok, popis] */
$check = function ($name, $what, $url, callable $test) use (&$checks) {
    if ($url === null) { $checks[] = ['name' => $name, 'what' => $what, 'state' => 'skip', 'msg' => 'Preskočené – chýbajú údaje z predchádzajúceho kroku.', 'code' => '', 'ms' => '', 'url' => '']; return null; }
    [$code, $ms, $json, $err] = md_probe($url);
    if ($code !== 200 || !$json) {
        $checks[] = ['name' => $name, 'what' => $what, 'state' => 'fail', 'msg' => $err ?: ('Sportnet vrátil HTTP ' . $code . ($json ? '' : ' bez platného JSON')), 'code' => $code, 'ms' => $ms, 'url' => $url];
        return null;
    }
    [$ok, $msg] = $test($json);
    $checks[] = ['name' => $name, 'what' => $what, 'state' => $ok === true ? 'ok' : ($ok === 'warn' ? 'warn' : 'fail'), 'msg' => $msg, 'code' => $code, 'ms' => $ms, 'url' => $url];
    return $json;
};


    // 1. sezóny
    $seasons = $check('Sezóny', 'zoznam sezón SFZ', API_SEASONS, fn($j) => !empty($j['seasons']) ? [true, count($j['seasons']) . ' sezón, posledná ' . end($j['seasons'])['name']] : [false, 'Chýba pole seasons']);
    $season = md_default_season();

    // 2. hľadanie klubu
    $clubs = $check('Hľadanie klubov', 'globálne hľadanie, úvodná stránka', sprintf(API_CLUB_SEARCH, 5, rawurlencode($q)), fn($j) => !empty($j['items']) ? [true, count($j['items']) . ' klubov, prvý: ' . ($j['items'][0]['name'] ?? '?')] : ['warn', 'Pre „' . $q . '“ sa nenašiel žiadny klub']);
    $club = (string)($clubs['items'][0]['_id'] ?? '');

    // 3. družstvá klubu (prvé, ktoré má zápasy)
    $teams = $check('Družstvá klubu', 'stránka klubu', $club ? sprintf(API_CLUB_TEAMS, rawurlencode($club), rawurlencode($season)) : null, fn($j) => !empty($j['teams']) ? [true, count($j['teams']) . ' družstiev v sezóne ' . $season] : ['warn', 'Klub nemá v sezóne ' . $season . ' žiadne družstvá']);
    $teamId = ''; $matches = [];
    foreach ((array)($teams['teams'] ?? []) as $t) {
        $l = sn_fetch_cached(sprintf(API_MATCHES, rawurlencode((string)$t['_id'])), 60);
        if (!empty($l['matches'])) { $teamId = (string)$t['_id']; $matches = $l['matches']; break; }
    }

    // 4. zápasy družstva
    $check('Zápasy družstva', 'zoznam zápasov, výsledky, program, forma', $teamId ? sprintf(API_MATCHES, rawurlencode($teamId)) : null, function ($j) {
        $m = $j['matches'] ?? [];
        if (!$m) return [false, 'Žiadne zápasy'];
        $need = ['_id', 'startDate', 'homeTeam', 'awayTeam', 'competitionPart'];
        $miss = array_diff($need, array_keys($m[0]));
        return $miss ? ['warn', 'Chýbajú polia: ' . implode(', ', $miss)] : [true, count($m) . ' zápasov, ' . count(array_filter($m, 'sn_is_played')) . ' odohraných'];
    });
    $played = array_values(array_filter($matches, 'sn_is_played'));
    $last = $played ? end($played) : null;
    [$compId, $partId] = $last ? sn_comp_ids($last) : ['', ''];

    // 5. zápis o stretnutí
    $doc = $check('Zápis o stretnutí', 'detail zápasu, zostavy, strelci, minúty', $last ? sprintf(API_MATCH, rawurlencode((string)$last['_id'])) : null, function ($j) {
        $ev = count((array)($j['protocol']['events'] ?? []));
        $nom = count((array)($j['nominations'] ?? []));
        $subs = count(array_filter((array)($j['protocol']['events'] ?? []), fn($e) => ($e['eventType'] ?? '') === 'substitution'));
        $msg = $nom . ' nominácie, ' . $ev . ' udalostí (' . $subs . ' striedaní), rozhodcovia: ' . count((array)($j['managers'] ?? [])) . ', štadión: ' . (!empty($j['sportGround']['name']) ? 'áno' : 'nie');
        return [$nom ? true : 'warn', $msg];
    });
    $player = null;
    foreach ((array)($doc['nominations'] ?? []) as $n) foreach ((array)($n['athletes'] ?? []) as $a) if (!$player && !empty($a['sportnetUser']['_id'])) $player = $a['sportnetUser'];

    // 6. oficiálna tabuľka
    $check('Oficiálna tabuľka', 'tabuľka súťaže, forma tímov', $compId && $partId ? sprintf(API_PART_TABLE, rawurlencode($compId), rawurlencode($partId)) : null, fn($j) => !empty($j['results']) ? [true, count($j['results']) . ' tímov, líder ' . ($j['results'][0]['team']['name'] ?? '?') . ' (' . ($j['results'][0]['stats']['points'] ?? '?') . ' b.)'] : ['warn', 'Súťaž nemá oficiálnu tabuľku – použije sa vypočítaná']);

    // 7. štatistiky hráčov (minúty)
    $check('Štatistiky hráčov', 'minúty, štarty, nástupy', $compId ? sprintf(API_COMP_PLAYERS, rawurlencode($compId), http_build_query(['teamId' => $teamId, 'partId' => $partId])) : null, function ($j) {
        $p = $j['players'] ?? [];
        if (!$p) return ['warn', 'Bez hráčov'];
        $withMin = count(array_filter($p, fn($x) => ($x['stats']['minutes'] ?? 0) > 0));
        return [true, count($p) . ' hráčov, ' . $withMin . ' s minútami'];
    });

    // 8.–10. hráč
    $pid = (string)($player['_id'] ?? '');
    $check('Kariéra hráča', 'profil hráča – kariéra', $pid ? sprintf(API_PLAYER_STATS, rawurlencode($pid)) : null, fn($j) => isset($j['overallStats']) ? [true, ($player['name'] ?? '') . ': ' . ((int)($j['overallStats']['match_appearances'] ?? $j['overallStats'][0]['match_appearances'] ?? 0)) . ' zápasov v kariére'] : ['warn', 'Chýba overallStats']);
    $check('Údaje hráča', 'meno, rok narodenia, fotka', $pid ? sprintf(API_USER, rawurlencode($pid)) : null, fn($j) => !empty($j['_id']) ? [true, trim(($j['name'] ?? '') . ' ' . ($j['surname'] ?? '')) . ($j['birthyear'] ?? false ? ', nar. ' . $j['birthyear'] : '') . ', fotka: ' . (!empty($j['photo']['public_url']) || !empty($j['formalPhoto']['public_url']) ? 'áno' : 'nie')] : [false, 'Hráč sa nenašiel']);
    $check('Kluby hráča', 'kluby a posledné zápasy hráča', $pid ? sprintf(API_USER_CLUBS, rawurlencode($pid)) : null, fn($j) => isset($j['ppoRelations']) ? [true, count($j['ppoRelations']) . ' väzieb na kluby/zväzy'] : ['warn', 'Chýba ppoRelations']);
    $surname = $player ? (string)(explode(' ', trim((string)$player['name']))[count(explode(' ', trim((string)$player['name']))) - 1] ?? '') : '';
    $check('Hľadanie hráčov', 'globálne hľadanie hráčov', $surname !== '' ? sprintf(API_PLAYER_SEARCH, rawurlencode($surname), 5) : null, fn($j) => !empty($j['users']) ? [true, ($j['total'] ?? count($j['users'])) . ' výsledkov pre „' . $surname . '“'] : ['warn', 'Bez výsledkov pre „' . $surname . '“']);
    $check('Zápasy hráča', 'posledné zápasy hráča za klub', $pid && $last ? sprintf(API_PLAYER_MATCHES, rawurlencode((string)($last['homeTeam']['_id'] === $teamId ? ($last['homeTeam']['appSpace'] ?? '') : ($last['awayTeam']['appSpace'] ?? ''))), rawurlencode($pid), 3) : null, fn($j) => isset($j['matches']) ? [true, count($j['matches']) . ' zápasov'] : ['warn', 'Chýba pole matches']);

    // 11. vzájomné zápasy
    $ha = (string)($last['homeTeam']['appSpace'] ?? ''); $aa = (string)($last['awayTeam']['appSpace'] ?? '');
    $check('Vzájomné zápasy', 'štatistika a H2H v detaile zápasu', $ha && $aa ? sprintf(API_H2H, rawurlencode($ha), rawurlencode($aa)) : null, fn($j) => isset($j['mutualMatches']) ? [true, count($j['mutualMatches']) . ' vzájomných zápasov'] : ['warn', 'Chýba mutualMatches']);

    // 12. logá a fotky cez server (povolené domény, overené presmerovania)
    $logoUrl = (string)($last['homeTeam']['logoUrl'] ?? ($matches[0]['homeTeam']['logoUrl'] ?? ''));
    if ($logoUrl === '') $checks[] = ['name' => 'Logá klubov', 'what' => 'logá v obrázkoch (cez server)', 'state' => 'skip', 'msg' => 'Zápasy nemajú logo.', 'code' => '', 'ms' => '', 'url' => ''];
    else {
        $t = microtime(true);
        $bin = md_safe_fetch_image($logoUrl);
        $host = (string)parse_url($logoUrl, PHP_URL_HOST);
        $checks[] = ['name' => 'Logá klubov', 'what' => 'logá v obrázkoch (cez server)', 'state' => $bin ? 'ok' : 'fail',
            'msg' => $bin ? 'Logo stiahnuté (' . round(strlen($bin) / 1024) . ' kB) z ' . $host : (md_image_host_allowed($host) ? 'Logo sa nepodarilo stiahnuť z ' . $host : 'Doména ' . $host . ' nie je povolená – pridajte ju do IMAGE_HOSTS v config.local.php'),
            'code' => '', 'ms' => (int)round((microtime(true) - $t) * 1000), 'url' => $logoUrl];
    }
    return $checks;
}
