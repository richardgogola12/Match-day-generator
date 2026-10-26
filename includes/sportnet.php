<?php
// =================================================================================
// Sportnet – celý zápis o stretnutí, súťaž (tabuľka, kolá), štatistiky a hráči
//
// Zdroje (Sportnet Sútaže API v2, https://sutaze.api.sportnet.online/api/v2/docs/):
//   API_MATCHES      zoznam zápasov družstva          API_MATCH   zápis o stretnutí
//   API_PART_TABLE   oficiálna tabuľka časti súťaže   API_COMP_PLAYERS  minúty, štarty, striedania hráčov
//   API_PLAYER_STATS kariérne štatistiky hráča
// Ak niektorý zdroj zlyhá, údaje sa dopočítajú zo zápisov o stretnutí.
// =================================================================================

/** Minúta z "mm:ss" alebo čísla */
function sn_minute($t) {
    if (is_numeric($t)) return (int)$t;
    return (int)explode(':', (string)$t)[0];
}

/**
 * Udalosti zápisu: góly, karty, striedania.
 * @return array [ ['kind'=>'goal'|'yellow'|'red'|'sub', 'minute', 'side', 'team', 'name', 'id', 'own', 'penalty', 'in', 'inId'] ]
 */
function sportnet_events(array $match, $homeId) {
    $out = [];
    foreach ((array)sn_val($match, ['protocol.events', 'events'], []) as $e) {
        if (!is_array($e)) continue;
        $type  = mb_strtolower((string)($e['eventType'] ?? $e['type'] ?? ''), 'UTF-8');
        $sub   = mb_strtolower((string)($e['type'] ?? $e['card'] ?? $e['cardType'] ?? ''), 'UTF-8');
        $team  = (string)sn_val($e, ['team', 'teamId', 'team._id'], '');
        $side  = $team === $homeId ? 'home' : 'away';
        $player = sn_val($e, ['player', 'athlete', 'playerOut', 'player_out'], null);
        $base = ['minute' => sn_minute($e['eventTime'] ?? $e['minute'] ?? $e['time'] ?? 0), 'team' => $team, 'side' => $side,
                 'name' => sn_person_name($player), 'id' => sn_person_id(is_array($player) ? $player : [])];

        if ($type === 'goal') {
            $own = $sub === 'dropped' || $sub === 'own' || !empty($e['ownGoal']);
            $out[] = $base + ['kind' => 'goal', 'own' => $own, 'penalty' => $sub === 'penalty', 'side' => $own ? ($side === 'home' ? 'away' : 'home') : $side];
        } elseif ($type === 'yellow_card' || $type === 'red_card' || preg_match('/card|karta/', $type) || in_array($type, ['yellow', 'red', 'yellow_card', 'red_card', 'second_yellow'], true)) {
            $k = $type === 'red_card' || ($type !== 'yellow_card' && preg_match('/red|cerven|červ|second|2/', $sub . ' ' . $type)) ? 'red' : 'yellow';
            $out[] = $base + ['kind' => $k, 'second' => (bool)preg_match('/second|2/', $sub)];
        } elseif (preg_match('/subst|striedan|change/', $type)) {
            $in = sn_val($e, ['replacement', 'playerIn', 'player_in', 'substitute', 'in'], null);
            $out[] = $base + ['kind' => 'sub', 'in' => sn_person_name($in), 'inId' => sn_person_id(is_array($in) ? $in : [])];
        }
    }
    usort($out, fn($a, $b) => $a['minute'] <=> $b['minute']);
    return $out;
}

/** Skóre "1:0" z rôznych tvarov (pole [1,0], {home,away}, reťazec) */
function sn_score_str($v) {
    if (is_string($v) && preg_match('/^\s*(\d+)\s*[:\-]\s*(\d+)\s*$/', $v, $m)) return $m[1] . ':' . $m[2];
    if (is_array($v)) {
        $h = $v[0] ?? $v['home'] ?? $v['homeTeam'] ?? null;
        $a = $v[1] ?? $v['away'] ?? $v['awayTeam'] ?? null;
        if (is_numeric($h) && is_numeric($a)) return (int)$h . ':' . (int)$a;
    }
    return '';
}

/**
 * Súhrn zápasu: polčas, diváci, rozhodcovia, delegát, štadión.
 */
function sportnet_report(array $match) {
    $half = '';
    foreach (['scoreByPhases.0', 'protocol.halftime', 'halftimeScore', 'halfTimeScore', 'protocol.halfTimeScore', 'score.halftime', 'periods.0.score'] as $p) {
        $half = sn_score_str(sn_val($match, [$p]));
        if ($half !== '') break;
    }
    $spect = sn_val($match, ['protocol.audience', 'protocol.spectators', 'spectators', 'attendance', 'protocol.audience', 'protocol.attendance'], '');

    // rozhodcovia a delegáti – zoznam osôb s funkciou
    $officials = [];
    foreach (['managers', 'referees', 'officials', 'protocol.managers', 'protocol.referees', 'delegates'] as $p) {
        $list = sn_val($match, [$p], []);
        if (!is_array($list)) continue;
        foreach ($list as $k => $o) {
            if (!is_array($o)) continue;
            $role = (string)sn_val($o, ['type.label', 'type.name', 'type', 'role', 'position', 'function'], is_string($k) ? $k : '');
            $name = sn_person_name($o);
            if ($name !== '') $officials[] = ['role' => $role, 'name' => $name];
        }
    }
    $referee = '';
    foreach ($officials as $o) {
        if (preg_match('/hlavn|^rozhod|main|^referee$|^r$/iu', $o['role'])) { $referee = $o['name']; break; }
    }
    if ($referee === '') foreach ($officials as $o) if (preg_match('/rozhod|referee/iu', $o['role'])) { $referee = $o['name']; break; }

    $venue = sn_val($match, ['sportGround.name', 'sportGround.displayName', 'venue.name', 'stadium.name', 'place'], '');
    return [
        'halftime'   => $half,
        'spectators' => is_scalar($spect) ? (string)$spect : '',
        'referee'    => $referee,
        'officials'  => $officials,
        'venue'      => is_scalar($venue) ? (string)$venue : '',
    ];
}

/**
 * Oficiálna tabuľka z poľa resultsTable (Sportnet ho má pri časti súťaže aj v dokumente zápasu).
 * Vracia [ ['id','name','p','w','d','l','gf','ga','pts'] … ] v oficiálnom poradí, alebo [] ak tabuľka chýba.
 * Štruktúra riadku nie je verejne zdokumentovaná – podporuje sa viac variantov názvov polí.
 */
function sn_results_table($doc) {
    if (!is_array($doc)) return [];
    $rows = sn_val($doc, ['resultsTable.results', 'resultsTable.table', 'competitionPart.resultsTable.results', 'part.resultsTable.results', 'resultsTable'], []);
    if (!is_array($rows) || !array_is_list($rows)) return [];
    $num = fn($r, array $paths) => is_numeric($v = sn_val($r, $paths)) ? (int)$v : null;
    $out = [];
    foreach ($rows as $i => $r) {
        if (!is_array($r)) continue;
        $team = sn_val($r, ['team'], []);
        $name = (string)sn_val(is_array($team) ? $team : [], ['displayName', 'name', 'organization.name'], '') ?: (string)sn_val($r, ['teamName', 'name'], '');
        $pts = $num($r, ['stats.points', 'points', 'pts', 'stats.pts']);
        if ($name === '' || $pts === null) continue;
        $out[] = [
            'id'   => (string)(is_array($team) ? ($team['_id'] ?? $team['id'] ?? '') : $team),
            'name' => $name,
            'logo' => (string)sn_val(is_array($team) ? $team : [], ['logo_public_url', 'logo', 'logoUrl', 'organization.logo_public_url'], ''),
            'p'    => $num($r, ['stats.matches.played', 'stats.played', 'played', 'matches']) ?? 0,
            'w'    => $num($r, ['stats.matches.won', 'stats.won', 'won', 'wins']) ?? 0,
            'd'    => $num($r, ['stats.matches.draw', 'stats.draw', 'draw', 'draws']) ?? 0,
            'l'    => $num($r, ['stats.matches.lost', 'stats.lost', 'lost', 'losses']) ?? 0,
            'gf'   => $num($r, ['stats.goals.given', 'stats.goalsGiven', 'goals.given', 'goalsFor', 'gf']) ?? 0,
            'ga'   => $num($r, ['stats.goals.received', 'stats.goalsReceived', 'goals.received', 'goalsAgainst', 'ga']) ?? 0,
            'pts'  => $pts,
            'order'=> $num($r, ['position', 'rank', 'order']) ?? ($i + 1),
            'form' => sn_table_form($r['matches'] ?? []),
            'note' => (string)sn_val($r, ['corrections.reason'], ''),
        ];
    }
    if (count($out) < 2) return [];
    usort($out, fn($a, $b) => $a['order'] <=> $b['order']);
    return $out;
}

/** Forma (posledných 5: V/R/P) z riadku oficiálnej tabuľky */
function sn_table_form($matches) {
    if (!is_array($matches) || !$matches) return [];
    usort($matches, fn($a, $b) => strcmp((string)($a['match']['startDate'] ?? ''), (string)($b['match']['startDate'] ?? '')));
    $f = [];
    foreach ($matches as $m) if (isset(['W' => 1, 'D' => 1, 'L' => 1][$m['result'] ?? ''])) $f[] = ['W' => 'V', 'D' => 'R', 'L' => 'P'][$m['result']];
    return array_slice($f, -5);
}

/** [ID súťaže, ID časti] zo zápasu v zozname */
function sn_comp_ids(array $m) {
    return [(string)($m['competition']['_id'] ?? $m['competitionId'] ?? ''), (string)($m['competitionPart']['_id'] ?? $m['partId'] ?? '')];
}

/** Dĺžka zápasu v minútach (z nastavení polčasov, inak 90) */
function sn_match_length(array $d) {
    $max = 0;
    foreach ((array)sn_val($d, ['settings.phases'], []) as $ph) {
        if (is_array($ph) && ($ph['endTime'] ?? 0) > ($ph['startTime'] ?? 0)) $max = max($max, (int)$ph['endTime']);
    }
    return $max > 0 ? (int)round($max / 60) : 90;
}

/**
 * Odohraté minúty hráčov jedného družstva v zápase zo zostavy a udalostí (striedania, červené karty).
 * @return array kľúč hráča => ['role' => 'start'|'sub'|'bench'|'listed', 'min' => int|null, 'in' => int|null, 'out' => int|null]
 *         'listed' = zápis nerozlišuje základ a lavičku, minúty sa nedajú určiť.
 */
function sn_match_minutes(array $lu, array $events, $teamId, $len) {
    $key = fn($id, $name) => $id !== '' ? 'id:' . $id : 'n:' . sn_name_key($name);
    $subIn = []; $subOut = []; $red = [];
    foreach ($events as $e) {
        if ($e['team'] !== $teamId) continue;
        $m = min($len, max(0, (int)$e['minute']));
        if ($e['kind'] === 'sub') {
            $subOut[$key($e['id'], $e['name'])] = $m;
            if (($e['in'] ?? '') !== '') $subIn[$key($e['inId'] ?? '', $e['in'])] = $m;
        } elseif ($e['kind'] === 'red') {
            $red[$key($e['id'], $e['name'])] = $m;
        }
    }
    $known = !$lu['guessed'];
    $out = [];
    foreach (array_merge($lu['start'], $lu['subs']) as $p) {
        $k = $key($p['id'], $p['name']);
        $alt = 'n:' . sn_name_key($p['name']);
        $in = $subIn[$k] ?? $subIn[$alt] ?? null;
        $off = $subOut[$k] ?? $subOut[$alt] ?? $red[$k] ?? $red[$alt] ?? null;
        if ($in !== null) {
            $end = $off !== null && $off >= $in ? $off : $len;
            $out[$k] = ['role' => 'sub', 'min' => max(1, $end - $in), 'in' => $in, 'out' => $off];
        } elseif (!$known) {
            $out[$k] = ['role' => 'listed', 'min' => null, 'in' => null, 'out' => $off];
        } elseif (!$p['sub']) {
            $out[$k] = ['role' => 'start', 'min' => $off !== null ? max(1, $off) : $len, 'in' => 0, 'out' => $off];
        } else {
            $out[$k] = ['role' => 'bench', 'min' => 0, 'in' => null, 'out' => null];
        }
    }
    return $out;
}

/**
 * Oficiálne štatistiky hráčov družstva zo Sportnetu (minúty, štarty, nástupy, striedania),
 * súčet za všetky súťaže družstva v zozname zápasov. Kľúč: ID hráča (Sportnet) aj "n:" + kľúč mena.
 */
function sn_official_player_stats(array $matches, $teamId) {
    $parts = [];
    foreach ($matches as $m) {
        [$c, $p] = sn_comp_ids($m);
        if ($c !== '') $parts[$c . '|' . $p] = [$c, $p];
    }
    if (!$parts) return [];
    $urls = array_map(fn($x) => sprintf(API_COMP_PLAYERS, rawurlencode($x[0]), http_build_query(array_filter(['teamId' => $teamId, 'partId' => $x[1]]))), array_values($parts));
    $out = [];
    foreach (sn_fetch_many($urls, 3600) as $res) {
        if (!is_array($res) || !is_array($res['players'] ?? null)) continue;
        foreach ($res['players'] as $pl) {
            $st = $pl['stats'] ?? null;
            if (!is_array($st)) continue;
            $id = (string)($pl['_id'] ?? '');
            $k = $id !== '' ? $id : 'n:' . sn_name_key($pl['name'] ?? '');
            $o = $out[$k] ?? ['id' => $id, 'name' => (string)($pl['name'] ?? ''), 'apps' => 0, 'starts' => 0, 'minutes' => 0, 'subs' => 0, 'goals' => 0, 'own' => 0, 'yellow' => 0, 'red' => 0];
            $o['apps']    += (int)($st['match_appearances'] ?? 0);
            $o['starts']  += (int)($st['match_starts'] ?? 0);
            $o['minutes'] += (int)($st['minutes'] ?? 0);
            $o['subs']    += (int)($st['substitutions'] ?? 0);
            $o['goals']   += (int)($st['goals'] ?? 0);
            $o['own']     += (int)($st['own_goals'] ?? 0);
            $o['yellow']  += (int)($st['yellow_cards'] ?? 0);
            $o['red']     += (int)($st['red_cards'] ?? 0) + (int)($st['yellow_cards_second'] ?? 0);
            $out[$k] = $o;
        }
    }
    foreach ($out as $k => $o) if ($o['name'] !== '') $out['n:' . sn_name_key($o['name'])] = &$out[$k];
    return $out;
}

/** Kariérne štatistiky hráča (všetky súťaže a sezóny) alebo null */
function sportnet_player_career($sportnetId) {
    if (!preg_match('/^[A-Za-z0-9_\-]{6,64}$/', (string)$sportnetId)) return null;
    $d = sn_fetch_cached(sprintf(API_PLAYER_STATS, rawurlencode($sportnetId)), 12 * 3600);
    $o = $d['overallStats'] ?? null;
    if (isset($o[0]) && is_array($o[0])) $o = $o[0];
    if (!is_array($o) || !isset($o['match_appearances'])) return null;
    return ['apps' => (int)$o['match_appearances'], 'starts' => (int)($o['match_starts'] ?? 0), 'minutes' => (int)($o['minutes'] ?? 0),
            'goals' => (int)($o['goals'] ?? 0), 'yellow' => (int)($o['yellow_cards'] ?? 0), 'red' => (int)($o['red_cards'] ?? 0) + (int)($o['yellow_cards_second'] ?? 0)];
}

/** Výsledok zápasu z pohľadu družstva: 'W' | 'D' | 'L' | '' */
function sn_result_for($m, $teamId) {
    $h = $m['homeTeam']['score'] ?? null;
    $a = $m['awayTeam']['score'] ?? null;
    if (!is_numeric($h) || !is_numeric($a)) return '';
    $home = ($m['homeTeam']['_id'] ?? '') === $teamId;
    $my = $home ? $h : $a; $op = $home ? $a : $h;
    return $my > $op ? 'W' : ($my < $op ? 'L' : 'D');
}

/** Je zápas odohratý so skóre? */
function sn_is_played($m) {
    return ($m['matchState'] ?? '') === 'ODOHRATY' && is_numeric($m['homeTeam']['score'] ?? null) && is_numeric($m['awayTeam']['score'] ?? null);
}

/** Číslo kola z názvu ("13. kolo" -> 13) */
function sn_round_no($m) {
    $r = (string)($m['round']['name'] ?? '');
    return preg_match('/(\d+)/', $r, $x) ? (int)$x[1] : 0;
}

/** Krátke zhrnutie zápasu pre stránky a obrázky */
function sn_match_brief($m) {
    return [
        'id'        => (string)($m['_id'] ?? ''),
        'round'     => (string)($m['round']['name'] ?? ''),
        'roundNo'   => sn_round_no($m),
        'date'      => (string)($m['startDate'] ?? ''),
        'state'     => (string)($m['matchState'] ?? ''),
        'played'    => sn_is_played($m),
        'home'      => ['id' => (string)($m['homeTeam']['_id'] ?? ''), 'name' => (string)($m['homeTeam']['name'] ?? ''), 'logo' => (string)($m['homeTeam']['logoUrl'] ?? ''), 'score' => $m['homeTeam']['score'] ?? null],
        'away'      => ['id' => (string)($m['awayTeam']['_id'] ?? ''), 'name' => (string)($m['awayTeam']['name'] ?? ''), 'logo' => (string)($m['awayTeam']['logoUrl'] ?? ''), 'score' => $m['awayTeam']['score'] ?? null],
        'competition' => (string)($m['competitionPart']['name'] ?? ''),
    ];
}

/**
 * Celá súťaž družstva: všetky zápasy časti súťaže (zo zoznamov zápasov všetkých účastníkov),
 * vypočítaná tabuľka a kolá.
 */
function sportnet_competition($teamId) {
    $cacheF = sn_cache_file('competition|' . $teamId);
    if (is_file($cacheF) && filemtime($cacheF) > time() - 1800 && is_array($c = json_decode((string)file_get_contents($cacheF), true))) return $c;

    $list = sn_fetch_cached(sprintf(API_MATCHES, rawurlencode($teamId)), 1800);
    if (!empty($list['error'])) return ['error' => 'Zápasy družstva sa nepodarilo načítať zo Sportnetu.'];
    $own = $list['matches'] ?? [];
    [$ownId, $ownName, $ownLogo] = sn_own_team($own, $teamId);

    // hlavná časť súťaže = najčastejšia (pohár a prípravné zápasy sa nezapočítajú)
    $partKey = fn($m) => (string)($m['competitionPart']['_id'] ?? $m['competitionPart']['name'] ?? '');
    $cnt = [];
    foreach ($own as $m) { $k = $partKey($m); if ($k !== '') $cnt[$k] = ($cnt[$k] ?? 0) + 1; }
    arsort($cnt);
    $part = (string)array_key_first($cnt);
    $partName = '';
    foreach ($own as $m) if ($partKey($m) === $part) { $partName = (string)($m['competitionPart']['name'] ?? ''); break; }

    // zoznamy zápasov všetkých súperov
    $teams = [];
    foreach ($own as $m) {
        if ($partKey($m) !== $part) continue;
        foreach (['homeTeam', 'awayTeam'] as $s) {
            $id = (string)($m[$s]['_id'] ?? '');
            if ($id !== '') $teams[$id] = ['id' => $id, 'name' => $m[$s]['name'] ?? '', 'logo' => $m[$s]['logoUrl'] ?? ''];
        }
    }
    $others = array_values(array_filter(array_keys($teams), fn($id) => $id !== $ownId && $id !== $teamId));
    $lists = sn_fetch_many(array_map(fn($id) => sprintf(API_MATCHES, rawurlencode($id)), $others), 1800);

    $all = [];
    foreach (array_merge([$own], array_map(fn($l) => $l['matches'] ?? [], array_filter($lists, 'is_array'))) as $ml) {
        foreach ($ml as $m) {
            if ($partKey($m) !== $part || empty($m['_id'])) continue;
            $all[$m['_id']] = $m;
            foreach (['homeTeam', 'awayTeam'] as $s) {
                $id = (string)($m[$s]['_id'] ?? '');
                if ($id !== '' && !isset($teams[$id])) $teams[$id] = ['id' => $id, 'name' => $m[$s]['name'] ?? '', 'logo' => $m[$s]['logoUrl'] ?? ''];
            }
        }
    }
    $all = array_values($all);
    usort($all, fn($a, $b) => strcmp($a['startDate'] ?? '', $b['startDate'] ?? ''));

    // tabuľka
    $tab = [];
    foreach ($teams as $id => $t) $tab[$id] = $t + ['p' => 0, 'w' => 0, 'd' => 0, 'l' => 0, 'gf' => 0, 'ga' => 0, 'pts' => 0, 'form' => [], 'note' => ''];
    foreach ($all as $m) {
        if (!sn_is_played($m)) continue;
        $h = (string)$m['homeTeam']['_id']; $a = (string)$m['awayTeam']['_id'];
        $hs = (int)$m['homeTeam']['score']; $as = (int)$m['awayTeam']['score'];
        foreach ([[$h, $hs, $as], [$a, $as, $hs]] as [$id, $f, $g]) {
            if (!isset($tab[$id])) continue;
            $r = $f > $g ? 'w' : ($f < $g ? 'l' : 'd');
            $tab[$id]['p']++; $tab[$id][$r]++; $tab[$id]['gf'] += $f; $tab[$id]['ga'] += $g;
            $tab[$id]['pts'] += $r === 'w' ? 3 : ($r === 'd' ? 1 : 0);
            $tab[$id]['form'][] = strtoupper($r === 'w' ? 'V' : ($r === 'd' ? 'R' : 'P'));
        }
    }
    $table = array_values($tab);
    usort($table, fn($x, $y) => [$y['pts'], $y['gf'] - $y['ga'], $y['gf'], $x['name']] <=> [$x['pts'], $x['gf'] - $x['ga'], $x['gf'], $y['name']]);

    // Oficiálna tabuľka zo Sportnetu (API časti súťaže, inak resultsTable v zápise posledného zápasu).
    // Obsahuje aj kontumácie a odpočty bodov; ak chýba, použije sa tabuľka vypočítaná z výsledkov.
    $official = false;
    $rt = [];
    $compId = '';
    foreach ($own as $m) if ($partKey($m) === $part) { $compId = sn_comp_ids($m)[0]; break; }
    if ($compId !== '' && $part !== '') $rt = sn_results_table(['resultsTable' => sn_fetch_cached(sprintf(API_PART_TABLE, rawurlencode($compId), rawurlencode($part)), 1800)]);
    $lastPlayed = array_values(array_filter($all, 'sn_is_played'));
    if (!$rt && $lastPlayed) $rt = sn_results_table(sn_fetch_cached(sprintf(API_MATCH, rawurlencode((string)end($lastPlayed)['_id'])), 1800));
    if ($rt) {
        $byId = []; $byName = [];
        foreach ($table as $r) { $byId[$r['id']] = $r; $byName[sn_name_key($r['name'])] = $r; }
        $table = array_map(function ($o) use ($byId, $byName) {
            $calc = $byId[$o['id']] ?? $byName[sn_name_key($o['name'])] ?? [];
            return ['id' => $calc['id'] ?? $o['id'], 'name' => $o['name'], 'logo' => $calc['logo'] ?? $o['logo'],
                    'p' => $o['p'], 'w' => $o['w'], 'd' => $o['d'], 'l' => $o['l'], 'gf' => $o['gf'], 'ga' => $o['ga'], 'pts' => $o['pts'],
                    'form' => $o['form'] ?: ($calc['form'] ?? []), 'note' => $o['note']];
        }, $rt);
        $official = true;
    }
    foreach ($table as $i => &$row) { $row['pos'] = $i + 1; $row['form'] = array_slice($row['form'], -5); $row['own'] = $row['id'] === $ownId || sn_name_key($row['name']) === sn_name_key($ownName); }
    unset($row);

    // kolá
    $rounds = [];
    foreach ($all as $m) {
        $b = sn_match_brief($m);
        $key = $b['roundNo'] ?: $b['round'];
        $rounds[$key] = $rounds[$key] ?? ['no' => $b['roundNo'], 'name' => $b['round'], 'matches' => []];
        $rounds[$key]['matches'][] = $b;
    }
    uasort($rounds, fn($a, $b) => $a['no'] <=> $b['no']);
    $rounds = array_values($rounds);
    // aktuálne kolo = posledné kolo s aspoň jedným odohratým zápasom; ďalšie = prvé neodohraté
    $last = null; $next = null;
    foreach ($rounds as $i => $r) {
        $playedAll = count(array_filter($r['matches'], fn($x) => $x['played'])) === count($r['matches']);
        if (array_filter($r['matches'], fn($x) => $x['played'])) $last = $i;
        if ($next === null && !$playedAll) $next = $i;
    }

    $res = ['team' => ['id' => $ownId, 'name' => $ownName, 'logo' => $ownLogo], 'competition' => $partName, 'table' => $table,
            'rounds' => $rounds, 'lastRound' => $last, 'nextRound' => $next, 'matches' => array_map('sn_match_brief', $all), 'computed' => !$official, 'official' => $official];
    @file_put_contents($cacheF, json_encode($res, JSON_UNESCAPED_UNICODE), LOCK_EX);
    return $res;
}

/**
 * Hráči a štatistiky družstva za sezónu.
 * Každý hráč: nr, name, key, listed (v zápise), apps (nastúpil), starts, minutes (null = neznáme), subIn, subOut,
 * goals, penalties, yellow, red, gk, captain, photo, matches[] (role start|sub|bench|listed, min, in, out).
 * Súčty minút a nástupov sú oficiálne zo Sportnetu (API_COMP_PLAYERS), ostatné zo zápisov o stretnutí.
 */
function sportnet_team($teamId) {
    $cacheF = sn_cache_file('team|' . $teamId);
    if (is_file($cacheF) && filemtime($cacheF) > time() - 3600 && is_array($c = json_decode((string)file_get_contents($cacheF), true))) return $c;

    $list = sn_fetch_cached(sprintf(API_MATCHES, rawurlencode($teamId)), 1800);
    if (!empty($list['error'])) return ['error' => 'Zápasy družstva sa nepodarilo načítať zo Sportnetu.'];
    $matches = $list['matches'] ?? [];
    [$ownId, $ownName, $ownLogo] = sn_own_team($matches, $teamId);

    $done = array_values(array_filter($matches, function ($m) {
        $state = $m['matchState'] ?? '';
        if ($state === 'ODOHRATY') return true;
        return $state !== 'NEZUCASTNENE_JEDNO_DRUZSTVO' && !empty($m['startDate']) && strtotime($m['startDate']) < time() - 3 * 3600;
    }));
    usort($done, fn($a, $b) => strcmp($a['startDate'] ?? '', $b['startDate'] ?? ''));
    $ids = array_map(fn($m) => (string)($m['_id'] ?? ''), $done);
    $details = sn_fetch_many(array_map(fn($id) => sprintf(API_MATCH, rawurlencode($id)), $ids), 12 * 3600);

    $players = [];
    $coach = '';
    $used = 0;
    $teamStats = ['p' => 0, 'w' => 0, 'd' => 0, 'l' => 0, 'gf' => 0, 'ga' => 0, 'clean' => 0, 'yellow' => 0, 'red' => 0,
                  'home' => ['p' => 0, 'w' => 0, 'd' => 0, 'l' => 0], 'away' => ['p' => 0, 'w' => 0, 'd' => 0, 'l' => 0], 'form' => [], 'bigWin' => null, 'spectators' => 0, 'spectMatches' => 0];
    $find = function ($id, $name) use (&$players) {
        $nk = sn_name_key($name);
        foreach ($players as $k => $p) if (($id !== '' && $id === $p['id']) || ($nk !== '' && $nk === sn_name_key($p['name']))) return $k;
        return null;
    };

    foreach ($done as $i => $m) {
        $homeId = (string)($m['homeTeam']['_id'] ?? '');
        $awayId = (string)($m['awayTeam']['_id'] ?? '');
        $side = $homeId === $ownId ? 'home' : ($awayId === $ownId ? 'away' : null);
        if (!$side) continue;
        $brief = sn_match_brief($m);
        $res = sn_result_for($m, $ownId);
        $my = $side === 'home' ? ($m['homeTeam']['score'] ?? null) : ($m['awayTeam']['score'] ?? null);
        $op = $side === 'home' ? ($m['awayTeam']['score'] ?? null) : ($m['homeTeam']['score'] ?? null);
        if ($res !== '') {
            $teamStats['p']++; $teamStats[strtolower(['W' => 'w', 'D' => 'd', 'L' => 'l'][$res])]++;
            $teamStats[$side]['p']++; $teamStats[$side][['W' => 'w', 'D' => 'd', 'L' => 'l'][$res]]++;
            $teamStats['gf'] += (int)$my; $teamStats['ga'] += (int)$op;
            if ((int)$op === 0) $teamStats['clean']++;
            $teamStats['form'][] = ['W' => 'V', 'D' => 'R', 'L' => 'P'][$res];
            $diff = (int)$my - (int)$op;
            if ($diff > 0 && (!$teamStats['bigWin'] || $diff > $teamStats['bigWin']['diff'])) $teamStats['bigWin'] = ['diff' => $diff, 'score' => $my . ':' . $op, 'opponent' => $side === 'home' ? $brief['away']['name'] : $brief['home']['name'], 'date' => $brief['date']];
        }

        $d = $details[$i] ?? null;
        if (!is_array($d) || !empty($d['error'])) continue;
        $rep = sportnet_report($d);
        if (is_numeric($rep['spectators'])) { $teamStats['spectators'] += (int)$rep['spectators']; $teamStats['spectMatches']++; }
        $lu = sportnet_lineups($d, $homeId, $awayId)[$side];
        $events = sportnet_events($d, $homeId);
        if (!$lu['start'] && !$lu['subs'] && !$events) continue;
        $used++;
        if ($lu['coach'] !== '') $coach = $lu['coach'];

        // minúty z udalostí zápisu (striedania, červené karty); bez rozlíšenia základu sa nedajú určiť
        $mins = sn_match_minutes($lu, $events, $side === 'home' ? $homeId : $awayId, sn_match_length($d));
        foreach (array_merge($lu['start'], $lu['subs']) as $p) {
            $k = $find($p['id'], $p['name']);
            if ($k === null) {
                $k = $p['id'] !== '' ? 'id:' . $p['id'] : 'n:' . sn_name_key($p['name']);
                $players[$k] = ['id' => $p['id'], 'key' => sn_name_key($p['name']), 'nr' => '', 'name' => $p['name'], 'listed' => 0, 'apps' => 0, 'starts' => 0, 'minutes' => null,
                                'subIn' => 0, 'subOut' => 0, 'goals' => 0, 'penalties' => 0, 'yellow' => 0, 'red' => 0, 'gk' => false, 'captain' => 0, 'nrs' => [], 'matches' => [], 'photo' => ''];
            }
            $mm = $mins[$p['id'] !== '' ? 'id:' . $p['id'] : 'n:' . sn_name_key($p['name'])] ?? ['role' => 'listed', 'min' => null, 'in' => null, 'out' => null];
            $pp = &$players[$k];
            $pp['listed']++;
            if ($mm['role'] !== 'bench') $pp['apps']++;
            if ($mm['role'] === 'start') $pp['starts']++;
            if ($mm['role'] === 'sub') $pp['subIn']++;
            if ($mm['out'] !== null && $mm['role'] !== 'bench') $pp['subOut']++;
            if ($mm['min'] !== null) $pp['minutes'] = ($pp['minutes'] ?? 0) + $mm['min'];
            if ($p['gk']) $pp['gk'] = true;
            if ($p['captain']) $pp['captain']++;
            if ($p['nr'] !== '') { $pp['nr'] = $p['nr']; $pp['nrs'][$p['nr']] = ($pp['nrs'][$p['nr']] ?? 0) + 1; }
            if (!empty($p['photo'])) $pp['photo'] = $p['photo'];
            $pp['name'] = $p['name'];
            $pp['matches'][] = ['id' => $brief['id'], 'date' => $brief['date'], 'round' => $brief['round'], 'home' => $side === 'home',
                'opponent' => $side === 'home' ? $brief['away']['name'] : $brief['home']['name'], 'score' => is_numeric($my) ? $brief['home']['score'] . ':' . $brief['away']['score'] : '',
                'result' => $res, 'role' => $mm['role'], 'min' => $mm['min'], 'in' => $mm['in'], 'out' => $mm['out'], 'goals' => 0, 'yellow' => 0, 'red' => 0, 'nr' => $p['nr']];
            unset($pp);
        }
        foreach ($events as $e) {
            if ($e['kind'] === 'sub') continue;
            if ($e['kind'] === 'goal' && ($e['own'] || $e['team'] !== $ownId)) continue;
            if ($e['kind'] !== 'goal' && $e['team'] !== $ownId) continue;
            $k = $find($e['id'], $e['name']);
            if ($k === null) continue;
            $field = $e['kind'] === 'goal' ? 'goals' : $e['kind'];
            $players[$k][$field]++;
            if ($e['kind'] === 'goal' && !empty($e['penalty'])) $players[$k]['penalties']++;
            if ($e['kind'] !== 'goal') $teamStats[$e['kind']]++;
            $last = count($players[$k]['matches']) - 1;
            if ($last >= 0 && $players[$k]['matches'][$last]['id'] === $brief['id']) $players[$k]['matches'][$last][$field]++;
        }
    }

    // Oficiálne súčty zo Sportnetu (minúty, nástupy, štarty) majú prednosť pred dopočtom zo zápisov.
    $official = sn_official_player_stats($matches, $ownId);
    foreach ($official as $ok => $o) {
        if (str_starts_with($ok, 'n:')) continue;
        $k = $find($o['id'], $o['name']);
        if ($k === null) {
            if ($o['apps'] === 0) continue;
            $k = 'id:' . $o['id'];
            $players[$k] = ['id' => $o['id'], 'key' => sn_name_key($o['name']), 'nr' => '', 'name' => $o['name'], 'listed' => 0, 'apps' => 0, 'starts' => 0, 'minutes' => null,
                            'subIn' => 0, 'subOut' => 0, 'goals' => 0, 'penalties' => 0, 'yellow' => 0, 'red' => 0, 'gk' => false, 'captain' => 0, 'nrs' => [], 'matches' => [], 'photo' => ''];
        }
        $pp = &$players[$k];
        $pp['apps'] = $o['apps']; $pp['starts'] = $o['starts']; $pp['minutes'] = $o['minutes'];
        // góly a karty: oficiálny súčet, ak je vyšší (zápis bez udalostí sa tak nestratí)
        $pp['goals'] = max($pp['goals'], $o['goals']); $pp['yellow'] = max($pp['yellow'], $o['yellow']); $pp['red'] = max($pp['red'], $o['red']);
        unset($pp);
    }

    $capKey = null;
    foreach ($players as $k => $p) if ($p['captain'] > 0 && ($capKey === null || $p['captain'] > $players[$capKey]['captain'])) $capKey = $k;
    $out = [];
    foreach ($players as $k => $p) {
        if ($p['nrs']) { arsort($p['nrs']); $p['nr'] = (string)array_key_first($p['nrs']); }
        unset($p['nrs']);
        $p['captain'] = $k === $capKey;
        $p['key'] = sn_name_key($p['name']);
        $out[] = $p;
    }
    usort($out, function ($a, $b) {
        $na = $a['nr'] === '' ? 999 : (int)$a['nr'];
        $nb = $b['nr'] === '' ? 999 : (int)$b['nr'];
        return $na <=> $nb ?: strcmp($a['key'], $b['key']);
    });
    $teamStats['form'] = array_slice($teamStats['form'], -5);

    $res = ['team' => ['id' => $ownId, 'name' => $ownName, 'logo' => $ownLogo], 'players' => $out, 'coach' => $coach, 'stats' => $teamStats,
            'matchesUsed' => $used, 'played' => count($done), 'competition' => $matches[0]['competitionPart']['name'] ?? '',
            'officialStats' => (bool)$official, 'hasMinutes' => (bool)array_filter($out, fn($p) => $p['minutes'] !== null)];
    @file_put_contents($cacheF, json_encode($res, JSON_UNESCAPED_UNICODE), LOCK_EX);
    return $res;
}

/** Rebríčky družstva (strelci, karty, zápasy) */
function sn_leaders(array $players, $field, $limit = 10) {
    $l = array_values(array_filter($players, fn($p) => ($p[$field] ?? 0) > 0));
    usort($l, fn($a, $b) => [$b[$field], $b['apps'] ?? 0] <=> [$a[$field], $a['apps'] ?? 0] ?: strcmp($a['key'], $b['key']));
    return array_slice($l, 0, $limit);
}

/** Aktuálna sezóna (podľa dátumov zo zoznamu sezón, inak podľa kalendára) */
function md_default_season() {
    $d = sn_fetch_cached(API_SEASONS, 24 * 3600);
    $now = gmdate('Y-m-d');
    $last = '';
    foreach ((array)($d['seasons'] ?? []) as $s) {
        $name = (string)($s['name'] ?? '');
        if ($name === '') continue;
        $last = $name;
        $from = substr((string)($s['dateFrom'] ?? ''), 0, 10); $to = substr((string)($s['dateTo'] ?? ''), 0, 10);
        if ($from !== '' && $to !== '' && $from <= $now && $now <= $to) return $name;
    }
    if ($last !== '') return $last;
    $y = (int)gmdate('Y') - ((int)gmdate('n') < 7 ? 1 : 0);
    return $y . '/' . ($y + 1);
}

// =================================================================================
// Odkazy medzi stránkami a generátorom
// =================================================================================

/** Odkaz na generátor s parametrami */
function md_gen_link($type, array $params) {
    return url('generator') . '?' . http_build_query(['type' => $type] + array_filter($params, fn($v) => $v !== null && $v !== ''));
}

/** Parametre zápasu pre generátor zo zhrnutia zápasu (sn_match_brief) */
function md_match_params(array $b, $myTeam = '') {
    $p = [
        'd1name' => $b['home']['name'], 'd2name' => $b['away']['name'],
        'd1url' => $b['home']['logo'], 'd2url' => $b['away']['logo'],
        'startDate' => $b['date'], 'competitionName' => $b['competition'], 'round' => $b['round'],
        'matchID' => $b['id'], 'team1ID' => $b['home']['id'], 'team2ID' => $b['away']['id'], 'myTeam' => $myTeam,
    ];
    if ($b['played']) { $p['score1'] = $b['home']['score']; $p['score2'] = $b['away']['score']; }
    return $p;
}

/** Parametre družstva (súpiska, tabuľka, kolo, štatistiky, hráč) */
function md_team_params($teamListId, array $team, $category, $season, $competition) {
    return ['team' => $teamListId, 'd1name' => $team['name'] ?? '', 'd1url' => $team['logo'] ?? '', 'category' => $category, 'season' => $season, 'competitionName' => $competition];
}

/** Zhrnutie zápasu z parametrov URL (generátor / detail zápasu) */
function md_brief_from_get() {
    $g = fn($k) => (string)($_GET[$k] ?? '');
    return ['id' => $g('matchID'), 'round' => $g('round'), 'roundNo' => (int)$g('round'), 'date' => $g('startDate'), 'state' => '',
            'played' => $g('score1') !== '' && $g('score2') !== '', 'competition' => $g('competitionName'),
            'home' => ['id' => $g('team1ID'), 'name' => $g('d1name'), 'logo' => $g('d1url'), 'score' => $g('score1')],
            'away' => ['id' => $g('team2ID'), 'name' => $g('d2name'), 'logo' => $g('d2url'), 'score' => $g('score2')]];
}

/** Formulár "V / R / P" ako farebné odznaky */
function md_form_badges(array $form) {
    $c = ['V' => 'success', 'R' => 'secondary', 'P' => 'danger'];
    return implode('', array_map(fn($f) => '<span class="form-badge text-bg-' . ($c[$f] ?? 'light') . '" title="' . ['V' => 'výhra', 'R' => 'remíza', 'P' => 'prehra'][$f] . '">' . $f . '</span>', $form));
}
