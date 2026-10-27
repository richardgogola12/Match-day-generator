<?php
// =================================================================================
// Sportnet – detail zápasu (vzájomné zápasy, forma, štadión, tabuľka), hráč podľa ID
// a odkazy medzi stránkami (klub, družstvo, hráč, zápas).
// =================================================================================

/** "fk-veca.futbalnet.sk" -> "fk-veca" (ID klubu v adrese stránky klubu) */
function sn_club_slug($appSpace) {
    return preg_replace('/\.futbalnet\.sk$/', '', (string)$appSpace);
}

/** Odkaz na stránku klubu */
function md_club_url($appSpace, $season = '', $teamId = '') {
    if ($appSpace === '' || $appSpace === null) return '';
    return url('match-day-season-detail') . '?' . http_build_query(array_filter(['team' => sn_club_slug($appSpace), 's' => $season, 't' => $teamId]));
}

/** Odkaz na prehľad družstva */
function md_team_url($teamId, $season = '', $cat = '', array $extra = []) {
    if ($teamId === '' || $teamId === null) return '';
    return url('druzstvo') . '?' . http_build_query(array_filter(['team' => $teamId, 's' => $season, 'cat' => $cat] + $extra, fn($v) => $v !== '' && $v !== null));
}

/** Odkaz na profil hráča (podľa ID zo Sportnetu, voliteľne s družstvom a sezónou) */
function md_player_url($sportnetId, $teamId = '', $season = '', $cat = '', $key = '') {
    $q = array_filter(['id' => $sportnetId, 'team' => $teamId, 's' => $season, 'cat' => $cat, 'p' => $key], fn($v) => $v !== '' && $v !== null);
    if (empty($q['id']) && (empty($q['team']) || empty($q['p']))) return '';
    return url('hrac') . '?' . http_build_query($q);
}

/** Odkaz na zápis o stretnutí (stačí ID zápasu, ostatné sa načíta zo Sportnetu) */
function md_match_url($matchId, $myTeam = '') {
    if ($matchId === '' || $matchId === null) return '';
    return url('zapas') . '?' . http_build_query(array_filter(['matchID' => $matchId, 'myTeam' => $myTeam]));
}

/** Logo klubu podľa appSpace */
function sn_club_logo($appSpace) {
    return $appSpace ? 'https://api.sportnet.online/data/ppo/' . rawurlencode($appSpace) . '/logo' : '';
}

/**
 * Zhrnutie zápasu priamo zo zápisu (detail zápasu) – rovnaký tvar ako sn_match_brief().
 */
function md_brief_from_doc(array $d) {
    $teams = array_values(array_filter((array)($d['teams'] ?? []), 'is_array'));
    $home = $teams[0] ?? []; $away = $teams[1] ?? [];
    foreach ($teams as $t) {
        $ha = $t['additionalProperties']['homeaway'] ?? '';
        if ($ha === 'home') $home = $t; elseif ($ha === 'away') $away = $t;
    }
    $score = $d['score'] ?? null;
    $played = is_array($score) && count($score) >= 2 && is_numeric($score[0]) && is_numeric($score[1]) && !empty($d['closed']);
    $side = function ($t, $i) use ($score, $played) {
        $club = (string)($t['appSpace'] ?? $t['organization']['_id'] ?? '');
        return ['id' => (string)($t['_id'] ?? ''), 'name' => (string)($t['name'] ?? $t['organization']['name'] ?? ''), 'club' => $club,
                'logo' => (string)($t['organization']['logo_public_url'] ?? '') ?: sn_club_logo($club), 'cat' => (string)($t['displayName'] ?? ''),
                'ageCategory' => (string)($t['ageCategory'] ?? ''), 'gender' => (string)($t['gender'] ?? ''),
                'score' => $played ? (int)$score[$i] : null];
    };
    $round = (string)($d['round']['name'] ?? '');
    return [
        'id' => (string)($d['_id'] ?? ''), 'issf' => (string)($d['__issfId'] ?? ''),
        'round' => $round, 'roundNo' => preg_match('/(\d+)/', $round, $x) ? (int)$x[1] : 0,
        'date' => (string)($d['startDate'] ?? ''), 'state' => '', 'played' => $played,
        'home' => $side($home, 0), 'away' => $side($away, 1),
        'competition' => (string)($d['competitionPart']['name'] ?? $d['competition']['name'] ?? ''),
        'competitionId' => (string)($d['competitionId'] ?? $d['competition']['_id'] ?? ''),
        'partId' => (string)($d['partId'] ?? $d['competitionPart']['_id'] ?? ''),
        'season' => (string)($d['season']['name'] ?? ''),
    ];
}

/** Výsledok z pohľadu družstva zo zhrnutia: 'V' | 'R' | 'P' | '' */
function sn_brief_result(array $b, $teamId, $club = '') {
    if (!$b['played']) return '';
    $isHome = $b['home']['id'] === $teamId || ($club !== '' && $b['home']['club'] === $club);
    $my = $isHome ? $b['home']['score'] : $b['away']['score'];
    $op = $isHome ? $b['away']['score'] : $b['home']['score'];
    return $my > $op ? 'V' : ($my < $op ? 'P' : 'R');
}

/**
 * Forma družstva: posledných $n odohratých zápasov pred dátumom $before (prázdny = teraz).
 * @return array [ brief + ['result' => V|R|P, 'isHome' => bool] ] od najnovšieho
 */
function sportnet_form($teamId, $before = '', $n = 5, $excludeId = '') {
    if ($teamId === '') return [];
    $list = sn_fetch_cached(sprintf(API_MATCHES, rawurlencode($teamId)), 1800);
    $limit = $before !== '' ? strtotime($before) : time() + 60;
    $out = [];
    foreach ((array)($list['matches'] ?? []) as $m) {
        if (!sn_is_played($m) || ($m['_id'] ?? '') === $excludeId) continue;
        if (strtotime($m['startDate'] ?? '') >= $limit) continue;
        $b = sn_match_brief($m);
        $b['result'] = sn_brief_result($b, $teamId);
        $b['isHome'] = $b['home']['id'] === $teamId;
        $out[] = $b;
    }
    usort($out, fn($a, $b) => strcmp($b['date'], $a['date']));
    return array_slice($out, 0, $n);
}

/**
 * Vzájomné zápasy dvoch klubov (iba rovnaká kategória ako tento zápas) a ich štatistika.
 * @return array ['matches' => [brief…] (od najnovšieho), 'played' => n,
 *                'home' => ['w','d','l','gf','ga','yellow','red'], 'away' => …]  (z pohľadu domácich tohto zápasu)
 */
function sportnet_h2h(array $b, $limit = 10) {
    $hc = $b['home']['club'] ?? ''; $ac = $b['away']['club'] ?? '';
    $empty = ['w' => 0, 'd' => 0, 'l' => 0, 'gf' => 0, 'ga' => 0, 'yellow' => 0, 'red' => 0];
    $res = ['matches' => [], 'played' => 0, 'home' => $empty, 'away' => $empty];
    if ($hc === '' || $ac === '' || $hc === $ac) return $res;
    $cacheF = sn_cache_file('h2h|' . $hc . '|' . $ac . '|' . ($b['home']['ageCategory'] ?? '') . '|' . ($b['home']['gender'] ?? '') . '|' . $b['id']);
    if (is_file($cacheF) && filemtime($cacheF) > time() - 3 * 3600 && is_array($c = json_decode((string)file_get_contents($cacheF), true))) return $c;
    if (($stale = md_bg_stale($cacheF, 3 * 3600, fn() => sportnet_h2h($b))) !== null) return $stale;

    $data = sn_fetch_cached(sprintf(API_H2H, rawurlencode($hc), rawurlencode($ac)), 6 * 3600);
    $list = array_values(array_filter((array)($data['mutualMatches'] ?? []), fn($m) => is_array($m) && sn_is_played($m) && ($m['_id'] ?? '') !== $b['id']));
    usort($list, fn($x, $y) => strcmp($y['startDate'] ?? '', $x['startDate'] ?? ''));
    $list = array_slice($list, 0, 25);
    // zápisy (kategória družstiev a karty)
    $docs = sn_fetch_many(array_map(fn($m) => sprintf(API_MATCH, rawurlencode((string)$m['_id'])), $list), 24 * 3600);
    $age = $b['home']['ageCategory'] ?? ''; $gender = $b['home']['gender'] ?? '';
    foreach ($list as $i => $m) {
        $d = is_array($docs[$i] ?? null) && empty($docs[$i]['error']) ? $docs[$i] : null;
        $clubOf = [];
        if ($d) {
            $ok = true;
            foreach ((array)($d['teams'] ?? []) as $t) {
                $clubOf[(string)($t['_id'] ?? '')] = (string)($t['appSpace'] ?? $t['organization']['_id'] ?? '');
                if ($age !== '' && ($t['ageCategory'] ?? $age) !== $age) $ok = false;
                if ($gender !== '' && ($t['gender'] ?? $gender) !== $gender) $ok = false;
            }
            if (!$ok) continue;
        }
        $mb = sn_match_brief($m);
        $homeIsOurs = $mb['home']['club'] === $hc;
        $hs = (int)$mb['home']['score']; $as = (int)$mb['away']['score'];
        $our = $homeIsOurs ? $hs : $as; $their = $homeIsOurs ? $as : $hs;
        $r = $our > $their ? 'w' : ($our < $their ? 'l' : 'd');
        $res['home'][$r]++; $res['away'][['w' => 'l', 'l' => 'w', 'd' => 'd'][$r]]++;
        $res['home']['gf'] += $our; $res['home']['ga'] += $their;
        $res['away']['gf'] += $their; $res['away']['ga'] += $our;
        if ($d) foreach (sportnet_events($d, $mb['home']['id']) as $e) {
            if ($e['kind'] !== 'yellow' && $e['kind'] !== 'red') continue;
            $club = $clubOf[$e['team']] ?? '';
            $who = $club === $hc ? 'home' : ($club === $ac ? 'away' : null);
            if ($who) $res[$who][$e['kind']]++;
        }
        $mb['result'] = ['w' => 'V', 'd' => 'R', 'l' => 'P'][$r];
        $res['matches'][] = $mb;
        if (count($res['matches']) >= $limit) break;
    }
    $res['played'] = count($res['matches']);
    @file_put_contents($cacheF, json_encode($res, JSON_UNESCAPED_UNICODE), LOCK_EX);
    return $res;
}

/** Oficiálna tabuľka súťaže zápasu so zvýraznenými oboma družstvami */
function sportnet_match_table(array $b, array $doc = []) {
    $rows = [];
    if ($b['competitionId'] !== '' && $b['partId'] !== '') {
        $rows = sn_results_table(['resultsTable' => sn_fetch_cached(sprintf(API_PART_TABLE, rawurlencode($b['competitionId']), rawurlencode($b['partId'])), 1800)]);
    }
    if (!$rows && $doc) $rows = sn_results_table($doc);
    foreach ($rows as $i => &$r) {
        $r['pos'] = $i + 1;
        $r['side'] = $r['id'] === $b['home']['id'] ? 'home' : ($r['id'] === $b['away']['id'] ? 'away' : (sn_name_key($r['name']) === sn_name_key($b['home']['name']) ? 'home' : (sn_name_key($r['name']) === sn_name_key($b['away']['name']) ? 'away' : '')));
        $r['own'] = $r['side'] !== '';
    }
    unset($r);
    return $rows;
}

/** Štadión zo zápisu */
function sportnet_venue(array $d) {
    $g = $d['sportGround'] ?? null;
    if (!is_array($g)) return null;
    $a = (array)($g['additionalData'] ?? []);
    $num = fn($k) => isset($a[$k]) && $a[$k] !== '' && $a[$k] !== null ? (string)$a[$k] : '';
    $addr = trim(implode(' ', array_filter([(string)($g['street'] ?? ''), (string)($g['number'] ?? '')])));
    $city = trim(implode(' ', array_filter([(string)($g['zip'] ?? ''), (string)($g['city'] ?? '')])));
    return [
        'name' => (string)($g['name'] ?? ''),
        'object' => (string)($g['sportObjectName'] ?? ''),
        'length' => $num('length'), 'width' => $num('width'),
        'capacity' => $num('totalCapacity'), 'guests' => $num('guestCapacity'), 'seats' => $num('seatingCapacity'), 'vip' => $num('vipSeatingCapacity'),
        'owner' => (string)($g['owner'] ?? ''),
        'address' => implode(', ', array_filter([$addr, $city])),
        'geo' => isset($g['geo']['coordinates'][1]) ? [(float)$g['geo']['coordinates'][1], (float)$g['geo']['coordinates'][0]] : null,
    ];
}

/** Delegované osoby (rozhodcovia, delegát, usporiadateľ) zo zápisu */
function sportnet_officials(array $d) {
    $out = [];
    foreach ((array)($d['managers'] ?? []) as $o) {
        if (!is_array($o)) continue;
        $name = sn_person_name($o['user'] ?? $o);
        if ($name === '') continue;
        $out[] = ['role' => (string)sn_val($o, ['type.label', 'type.value', 'type', 'role'], 'Funkcionár'), 'name' => $name];
    }
    return $out;
}

/** Hráč zo Sportnetu: meno, fotka, rok narodenia, kluby */
function sportnet_user($sportnetId) {
    if (!preg_match('/^[A-Za-z0-9_\-]{6,64}$/', (string)$sportnetId)) return null;
    $u = sn_fetch_cached(sprintf(API_USER, rawurlencode($sportnetId)), 24 * 3600);
    if (!is_array($u) || !empty($u['error']) || empty($u['_id'])) return null;
    $rel = sn_fetch_cached(sprintf(API_USER_CLUBS, rawurlencode($sportnetId)), 24 * 3600);
    $clubs = [];
    foreach ((array)($rel['ppoRelations'] ?? []) as $r) {
        $ppo = (string)($r['ppo'] ?? '');
        if ($ppo !== '' && $ppo !== 'futbalsfz.sk' && (str_contains($ppo, '.futbalnet.sk') || str_starts_with($ppo, 'issf_club_'))) $clubs[] = $ppo;
    }
    $photo = '';
    foreach (['photo', 'formalPhoto'] as $k) if (!empty($u[$k]['public_url']) && ($u[$k]['public'] ?? true)) { $photo = (string)$u[$k]['public_url']; break; }
    return [
        'id' => (string)$u['_id'],
        'name' => trim(($u['name'] ?? '') . ' ' . ($u['surname'] ?? '')),
        'birthyear' => (int)($u['birthyear'] ?? 0) ?: null,
        'age' => (int)($u['age'] ?? 0) ?: null,
        'citizenship' => (string)($u['citizenship'] ?? ''),
        'photo' => $photo,
        'clubs' => array_values(array_unique($clubs)),
    ];
}

/**
 * Posledné zápasy hráča naprieč klubmi (z /player-matches) – kvôli určeniu aktuálneho družstva.
 * @return array [ ['brief' => …, 'teamId', 'club', 'cat', 'season'] ] od najnovšieho
 */
function sportnet_player_recent($sportnetId, array $clubs, $limit = 5) {
    if (!$clubs) return [];
    $urls = array_map(fn($c) => sprintf(API_PLAYER_MATCHES, rawurlencode($c), rawurlencode($sportnetId), $limit), $clubs);
    $out = [];
    foreach (sn_fetch_many($urls, 6 * 3600) as $i => $res) {
        foreach ((array)($res['matches'] ?? []) as $m) {
            if (!is_array($m)) continue;
            $club = $clubs[$i];
            $team = null;
            foreach ((array)($m['teams'] ?? []) as $t) if (($t['appSpace'] ?? $t['organization']['_id'] ?? '') === $club) $team = $t;
            $b = md_brief_from_doc($m);
            $out[] = ['brief' => $b, 'teamId' => (string)($team['_id'] ?? ''), 'club' => $club, 'cat' => (string)($team['displayName'] ?? ''), 'season' => (string)($m['season']['name'] ?? '')];
        }
    }
    usort($out, fn($a, $b) => strcmp($b['brief']['date'], $a['brief']['date']));
    return $out;
}
require_once __DIR__ . '/create.php';
