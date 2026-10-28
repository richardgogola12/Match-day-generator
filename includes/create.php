<?php
/**
 * „Vytvoriť obrázok“ – plávajúce tlačidlo a inteligentný panel.
 *
 * Každá stránka pridá skupiny (zápas, družstvo, hráč, klub) cez md_create_add().
 * Pätička vykreslí plávajúce tlačidlo a panel; odporúčané obrázky (podľa dátumu zápasu,
 * dňa v týždni, začiatku mesiaca …) sú navrchu. Riadky zápasov v zoznamoch otvárajú ten istý
 * panel cez atribút data-md-create (md_create_attr).
 *
 * Skupina: ['key', 'kind' => match|team|player|club, 'title', 'sub', 'logos' => [], 'link', 'items' => [ položky ]]
 * Položka: ['type', 'label', 'icon', 'hint', 'url', 'rec' => bool, 'why' => dôvod odporúčania]
 */

$GLOBALS['md_create'] = [];

function md_create_add(?array $group, $first = false) {
    if (!$group || empty($group['items'])) return;
    foreach ($GLOBALS['md_create'] as $g) if ($g['key'] === $group['key']) return;
    if ($first) array_unshift($GLOBALS['md_create'], $group); else $GLOBALS['md_create'][] = $group;
}

/** skupina patrí jednému družstvu na stránke klubu (panel ukáže skupiny práve zobrazeného družstva) */
function md_create_pane(?array $g, $pane) { return $g ? $g + ['pane' => (string)$pane] : null; }

/** tlačidlo, ktoré otvorí panel pre jednu skupinu */
function md_create_btn(?array $group, $label = 'Ďalšie obrázky', $cls = 'btn btn-sm btn-outline-secondary rounded-pill') {
    if (!$group) return '';
    return '<button type="button" class="' . $cls . ' mdc-open"' . md_create_attr($group) . ' title="Všetky obrázky k tomuto zápasu"><i class="bi bi-magic' . ($label !== '' ? ' me-1' : '') . '"></i>' . ($label !== '' ? plain($label) : '<span class="visually-hidden">Ďalšie obrázky</span>') . '</button>';
}

/** atribút pre tlačidlo, ktoré otvorí panel pre jednu skupinu (napr. riadok zápasu) */
function md_create_attr(?array $group) {
    return $group ? ' data-md-create="' . htmlspecialchars(json_encode($group, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), ENT_QUOTES, 'UTF-8') . '"' : '';
}

/** počet dní do dátumu (záporné = v minulosti), podľa kalendárnych dní v Bratislave */
function md_days_from_now($iso) {
    if (!$iso) return null;
    try {
        $tz = new DateTimeZone('Europe/Bratislava');
        $d = (new DateTime($iso))->setTimezone($tz)->setTime(0, 0);
        $today = (new DateTime('now', $tz))->setTime(0, 0);
        return (int)$today->diff($d)->format('%r%a');
    } catch (Throwable $e) { return null; }
}

function md_when_txt($days) {
    if ($days === null) return '';
    if ($days === 0) return 'dnes';
    if ($days === 1) return 'zajtra';
    if ($days === -1) return 'včera';
    if ($days > 1) return 'o ' . $days . ' ' . ($days < 5 ? 'dni' : 'dní');
    return 'pred ' . -$days . ' dňami';
}

function md_round_txt($r) {
    $r = (string)$r;
    return $r === '' ? '' : (preg_match('/kolo/i', $r) ? $r : $r . '. kolo');
}

/**
 * Zápas (zhrnutie sn_match_brief / md_brief_from_doc).
 * $ctx: category, season, myTeam
 */
function md_create_match(array $b, array $ctx = []) {
    if (empty($b['home']['name'])) return null;
    $my = (string)($ctx['myTeam'] ?? '');
    $p = md_match_params($b, $my) + array_filter(['category' => $ctx['category'] ?? '', 'season' => $ctx['season'] ?? ($b['season'] ?? '')]);
    $side = $my !== '' && $my === ($b['away']['id'] ?? '') ? 'away' : 'home';
    $hasId = ($b['id'] ?? '') !== '';
    $days = md_days_from_now($b['date'] ?? '');
    $when = md_when_txt($days);
    $it = fn($type, $label, $icon, $hint, $extra = [], $rec = false, $why = '') =>
        ['type' => $type, 'label' => $label, 'icon' => $icon, 'hint' => $hint, 'url' => md_gen_link($type, $p + $extra), 'rec' => $rec, 'why' => $why];
    $items = [];
    if (!empty($b['played'])) {
        $fresh = $days !== null && $days >= -3;
        $items[] = $it('result', 'Výsledok', 'bi-trophy', 'konečné skóre', [], $fresh, 'zápas sa hral ' . $when);
        if ($hasId) {
            $items[] = $it('goals', 'Strelci', 'bi-list-ol', 'skóre a strelci s minútami', [], $fresh, 'zápas sa hral ' . $when);
            $items[] = $it('report', 'Súhrn zápasu', 'bi-file-text', 'polčas, góly, karty, rozhodca, diváci');
            $items[] = $it('lineup', 'Zostava', 'bi-person-lines-fill', 'základ a náhradníci', ['side' => $side]);
        }
    } else {
        $soon = $days !== null && $days >= 0 && $days <= 7;
        $items[] = $it('matchday', 'Match day', 'bi-calendar-event', 'pozvánka na zápas', [], $soon, 'zápas je ' . $when);
        $items[] = $it('poster', 'Plagát A4', 'bi-file-earmark-richtext', 'pozvánka na vytlačenie (PDF) s QR kódom', [], $days !== null && $days >= 3 && $days <= 10, 'zápas je ' . $when . ' – stihnete ho vyvesiť');
        if ($hasId) $items[] = $it('lineup', 'Zostava', 'bi-person-lines-fill', 'keď je zostava v zápise (krátko pred zápasom)', ['side' => $side], $days !== null && $days >= 0 && $days <= 1, 'zápas je ' . $when);
    }
    if ($hasId) {
        $items[] = $it('h2h', 'Vzájomné zápasy', 'bi-bar-chart-steps', 'bilancia a posledné vzájomné zápasy', [], !$b['played'] && $days !== null && $days >= 0 && $days <= 3, 'pred zápasom');
        $items[] = $it('form', 'Forma', 'bi-graph-up-arrow', 'posledných 5 zápasov ' . ($side === 'away' ? 'hostí' : 'domácich'), ['side' => $side]);
    }
    [$dT, $tT] = format_match_date($b['date'] ?? '');
    $score = !empty($b['played']) ? $b['home']['score'] . ':' . $b['away']['score'] : '';
    return [
        'key' => 'm:' . ($b['id'] ?: md5($b['home']['name'] . $b['away']['name'] . ($b['date'] ?? ''))), 'kind' => 'match', 'cat' => (string)($ctx['category'] ?? ''),
        'title' => $b['home']['name'] . ($score !== '' ? ' ' . $score . ' ' : ' – ') . $b['away']['name'],
        'sub' => implode(' · ', array_filter([$ctx['category'] ?? '', md_round_txt($b['round'] ?? ''), trim($dT . ($score === '' ? ' ' . $tT : '')), $when])),
        'logos' => array_values(array_filter([$b['home']['logo'] ?? '', $b['away']['logo'] ?? ''])),
        'link' => $hasId ? md_match_url($b['id'], $my) : '',
        'items' => $items,
    ];
}

/** Družstvo: súpiska, tabuľka, kolo, štatistiky, hráč mesiaca. $C = sportnet_competition() */
function md_create_team($teamId, array $team, $cat, $season, $competition, ?array $C = null) {
    $tp = md_team_params($teamId, $team, $cat, $season, $competition);
    $it = fn($type, $label, $icon, $hint, $extra = [], $rec = false, $why = '') =>
        ['type' => $type, 'label' => $label, 'icon' => $icon, 'hint' => $hint, 'url' => md_gen_link($type, $tp + $extra), 'rec' => $rec, 'why' => $why];
    $rounds = $C['rounds'] ?? [];
    $last = isset($C['lastRound']) && $C['lastRound'] !== null ? ($rounds[$C['lastRound']] ?? null) : null;
    $next = isset($C['nextRound']) && $C['nextRound'] !== null ? ($rounds[$C['nextRound']] ?? null) : null;
    $lastDays = null; $nextDays = null;
    if ($last) foreach ($last['matches'] as $m) if (!empty($m['played'])) { $d = md_days_from_now($m['date']); $lastDays = $lastDays === null ? $d : max($lastDays, $d); }
    if ($next) foreach ($next['matches'] as $m) { $d = md_days_from_now($m['date']); if ($d !== null && $d >= 0) $nextDays = $nextDays === null ? $d : min($nextDays, $d); }
    $freshRound = $lastDays !== null && $lastDays >= -3;

    $items = [];
    if ($last) $items[] = $it('round', 'Výsledky ' . md_round_txt($last['no'] ?: $last['name']), 'bi-calendar-week', 'všetky zápasy kola s výsledkami', ['roundNo' => $last['no']], $freshRound, 'kolo sa dohralo ' . md_when_txt($lastDays));
    if ($next && $next !== $last) $items[] = $it('round', 'Program ' . md_round_txt($next['no'] ?: $next['name']), 'bi-calendar2-event', 'zápasy kola s časom výkopu', ['roundNo' => $next['no']], $nextDays !== null && $nextDays <= 5, 'kolo sa hrá ' . md_when_txt($nextDays));
    $items[] = $it('table', 'Tabuľka', 'bi-list-ol', 'poradie súťaže', [], $freshRound, 'po poslednom kole');
    $items[] = $it('stats', 'Štatistiky', 'bi-bar-chart-line', 'strelci, zápasy a karty');
    // hráč mesiaca: na začiatku mesiaca za predchádzajúci mesiac
    $tz = new DateTimeZone('Europe/Bratislava');
    $now = new DateTime('now', $tz);
    $early = (int)$now->format('j') <= 7;
    $prev = (clone $now)->modify('first day of last month')->format('Y-m');
    $mNames = ['', 'január', 'február', 'marec', 'apríl', 'máj', 'jún', 'júl', 'august', 'september', 'október', 'november', 'december'];
    $items[] = $early
        ? $it('month', 'Hráč mesiaca – ' . $mNames[(int)substr($prev, 5)], 'bi-star', 'podľa gólov, minút alebo zápasov', ['month' => $prev], true, 'mesiac sa práve skončil')
        : $it('month', 'Hráč / strelec mesiaca', 'bi-star', 'podľa gólov, minút alebo zápasov');
    $items[] = $it('squad', 'Súpiska', 'bi-people', 'všetci hráči sezóny s číslami');
    // koniec sezóny / časti: po poslednom kole alebo v mesiacoch, keď sa súťaž končí
    $mon = (int)$now->format('n');
    $over = $C && !$next && $last;
    $seasonEnd = $over || in_array($mon, [6, 7, 11, 12], true);
    $items[] = $it('season', 'Koniec sezóny', 'bi-flag', 'miesto, body, skóre a najlepší strelec', [], $seasonEnd, $over ? 'súťaž sa dohrala' : ($mon >= 11 ? 'jesenná časť sa končí' : 'sezóna sa končí'));
    return [
        'key' => 't:' . $teamId, 'kind' => 'team',
        'title' => trim(($team['name'] ?? '') . ' ' . $cat),
        'sub' => implode(' · ', array_filter([$competition, $season])),
        'logos' => array_values(array_filter([$team['logo'] ?? ''])),
        'link' => md_team_url($teamId, $season, $cat),
        'items' => $items,
    ];
}

/** Hráč: profil, hráč zápasu, jubileum, narodeniny */
function md_create_player(array $pp, ?array $P, $name, $bdAge = 0) {
    if (empty($pp['team'])) return null;
    $it = fn($type, $label, $icon, $hint, $extra = [], $rec = false, $why = '') =>
        ['type' => $type, 'label' => $label, 'icon' => $icon, 'hint' => $hint, 'url' => md_gen_link($type, $pp + $extra), 'rec' => $rec, 'why' => $why];
    $items = [$it('player', 'Profil hráča', 'bi-person-vcard', 'fotka, číslo a štatistiky sezóny')];
    $lastM = null;
    foreach (array_reverse($P['matches'] ?? []) as $m) if (($m['score'] ?? '') !== '' && ($m['role'] ?? '') !== 'bench') { $lastM = $m; break; }
    if ($lastM) {
        $d = md_days_from_now($lastM['date'] ?? '');
        $good = ($lastM['goals'] ?? 0) > 0;
        $items[] = $it('motm', 'Hráč zápasu', 'bi-award', 'vs. ' . $lastM['opponent'] . ' ' . $lastM['score'] . ($good ? ' · ' . $lastM['goals'] . ' g.' : ''), ['matchRef' => $lastM['id']], $d !== null && $d >= -3 && $good, 'skóroval ' . md_when_txt($d));
    }
    if ($P) {
        $round = fn($n) => $n > 0 && ($n === 10 || $n === 25 || $n % 50 === 0);
        $items[] = $it('milestone', 'Jubileum – ' . (int)$P['apps'] . '. zápas', 'bi-trophy', 'zápasy v sezóne (kariéru zadáte ručne)', ['mvalue' => $P['apps'], 'mkind' => 'apps'], $round((int)$P['apps']), 'okrúhle číslo');
        if ($P['goals'] > 0) $items[] = $it('milestone', 'Jubileum – ' . (int)$P['goals'] . '. gól', 'bi-bullseye', 'góly v sezóne', ['mvalue' => $P['goals'], 'mkind' => 'goals'], $round((int)$P['goals']), 'okrúhle číslo');
    }
    $items[] = $it('birthday', 'Narodeniny' . ($bdAge ? ' – ' . $bdAge . '.' : ''), 'bi-cake2', 'blahoželanie s vekom a prianím', $bdAge ? ['age' => $bdAge] : []);
    return ['key' => 'p:' . ($pp['team'] ?? '') . ':' . ($pp['player'] ?? ''), 'kind' => 'player', 'title' => $name, 'sub' => trim(($pp['d1name'] ?? '') . ' · ' . ($pp['category'] ?? ''), ' ·'), 'logos' => [], 'link' => '', 'items' => $items];
}

/** Klub: víkend klubu (piatok – nedeľa program, pondelok – utorok výsledky) */
function md_create_club($slug, $name, $logo, $season) {
    if ($slug === '') return null;
    $tz = new DateTimeZone('Europe/Bratislava');
    $now = new DateTime('now', $tz);
    $dow = (int)$now->format('N'); // 1 = pondelok
    $monday = (clone $now)->modify('monday this week')->format('Y-m-d');
    $prevMonday = (clone $now)->modify('monday last week')->format('Y-m-d');
    $base = ['club' => $slug, 'season' => $season, 'd1name' => $name, 'd1url' => $logo];
    $items = [];
    if ($dow <= 2) {
        $items[] = ['type' => 'weekend', 'label' => 'Výsledky víkendu', 'icon' => 'bi-calendar2-check', 'hint' => 'všetky družstvá klubu, minulý týždeň', 'url' => md_gen_link('weekend', $base + ['week' => $prevMonday]), 'rec' => true, 'why' => 'po víkende'];
        $items[] = ['type' => 'weekend', 'label' => 'Program tohto týždňa', 'icon' => 'bi-calendar2-week', 'hint' => 'zápasy všetkých družstiev', 'url' => md_gen_link('weekend', $base + ['week' => $monday]), 'rec' => false, 'why' => ''];
    } else {
        $items[] = ['type' => 'weekend', 'label' => 'Program víkendu', 'icon' => 'bi-calendar2-week', 'hint' => 'zápasy všetkých družstiev klubu', 'url' => md_gen_link('weekend', $base + ['week' => $monday]), 'rec' => $dow >= 4, 'why' => 'blíži sa víkend'];
        $items[] = ['type' => 'weekend', 'label' => 'Výsledky minulého víkendu', 'icon' => 'bi-calendar2-check', 'hint' => 'všetky družstvá klubu', 'url' => md_gen_link('weekend', $base + ['week' => $prevMonday]), 'rec' => false, 'why' => ''];
    }
    return ['key' => 'c:' . $slug, 'kind' => 'club', 'title' => $name ?: 'Klub', 'sub' => 'Celý klub · ' . $season, 'logos' => array_values(array_filter([$logo])), 'link' => md_club_url($slug, $season), 'items' => $items];
}
