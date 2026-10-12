<?php
    // The Safeguard's register: the easter eggs of the site an account has
    // found, shown in its profile (/account/), and the ranking of the hunters
    // (/lowcy/). Part of the login, loaded by inc/auth.php.

    // ---- The Safeguard's register ------------------------------------------------
    // A find is noted in the browser first (js/safeguard.js), so a visitor who
    // is not logged in keeps it there, and goes to inc/data/finds.json, ID =>
    // [key => time], once the browser knows of an account (account.php?finds).
    // js/safeguard.js lists the same keys with their names, for its toast.

    // key => [name, what it was, once found; a hint, until then]
    const SAFEGUARD_FINDS = [
        'netsphere' => ['Nielegalny mieszkaniec', 'Prośba o dostęp do Netsfery bez genu terminala sieciowego. Odrzucona.', 'Jedno słowo otwiera drzwi do sieci. Nie wpuszczą cię, ale zapytać można.'],
        'gbe'       => ['Strzał z Emitera', 'Strzał z Emitera Promienia Grawitacyjnego, oddany z kropki statusu.', 'Kropka na stronie głównej skrywa broń. Potrzeba cierpliwości.'],
        'lost'      => ['Zgubiony w Megastrukturze', 'Adres, który prowadzi donikąd, i miejsce w Megastrukturze, w którym się kończy.', 'Zabłądź tam, gdzie nie ma drogi.'],
        'console'   => ['Intruz w kodzie', 'Zgłoszenie się Safeguardowi z konsoli przeglądarki.', 'Safeguard mówi do tych, którzy zaglądają pod maskę strony.'],
        'silicon'   => ['Forma krzemowa', 'Infekcja strony starym kodem i dezynfekcja przez Safeguarda.', 'Kod znany od 1986 roku.'],
        'header'    => ['Podsłuch', 'Hasło podsłuchane w nagłówkach serwera i podane terminalowi.', 'Nie wszystko, co mówi serwer, widać na stronie.'],
        'lore'      => ['Kronikarz', 'Pięć wpisów z archiwum Megastruktury, przeczytanych w terminalu Safeguarda.', 'Terminal zna więcej imion, niż wypisuje jego pomoc.'],
        'offline'   => ['Odcięty od sieci', 'Zerwane połączenie z siecią i powrót do niej.', 'Odetnij się od sieci i spróbuj wrócić.'],
        'morse'     => ['Sygnał zwrotny', 'Słowo wystukane Morse\'em na motcie strony głównej i odpowiedź Safeguarda.', 'Motto na górze strony głównej nie tylko nadaje. Wołanie o pomoc to dobry początek.'],
        'idle'      => ['Czuwanie', 'Strona główna zasnęła w bezruchu, a Sanakan czuwała dalej.', 'Zostaw stronę główną na dłużej w spokoju.'],
        'regen'     => ['Regeneracja', 'Trzy strzały z Emitera pod rząd rozłupały logo, a Sanakan się zregenerowała.', 'Jeden strzał z Emitera to za mało, żeby zranić Safeguarda.'],
        'killy'     => ['Spotkanie z Killym', 'Killy przeszedł przez stronę i dał się zatrzymać. Nic nie powiedział.', 'Czasem ktoś przechodzi dołem strony. Im więcej sekretów w rejestrze, tym częściej. Potem potrzeba refleksu.']
    ];

    // The finds of a time of the year, the same way (js/safeguard.js, js/home.js),
    // listed apart and left out of the count, as the rest of the year nobody
    // can make them; the hint says when they come back (and the register how
    // soon, safeguardHolidayIn()).
    const SAFEGUARD_SEASONAL = [
        'xmas'       => ['Święta w Megastrukturze', 'Czapka Mikołaja strącona z głowy Sanakan. Safeguard nie obchodzi świąt.', 'Wraca co roku od 20 grudnia do Trzech Króli.'],
        'newyear'    => ['Nowy cykl', 'Fajerwerki z glifów odpalone na Sylwestra.', 'Wraca co roku 31 grudnia i 1 stycznia.'],
        'easter'     => ['Pisanki z Megastruktury', 'Pięć pisanek rozbitych na pięciu stronach w czasie Wielkanocy.', 'Wraca co roku od Niedzieli Palmowej do Poniedziałku Wielkanocnego.'],
        'aprilfools' => ['Punkt z Safeguardem', 'Piłka przepuszczona przez Safeguarda w ping pongu na prima aprilis.', 'Wraca co roku 1 kwietnia. Safeguard zaprasza wtedy do gry.'],
        'birthday'   => ['Sto lat', 'Świeczki na torcie zdmuchnięte w urodziny bota.', 'Wraca co roku 13 października, w urodziny bota.'],
        'halloween'  => ['Noc form krzemowych', 'Forma krzemowa złapana na krawędzi strony w Halloween.', 'Wraca co roku 31 października.'],
        'friday13'   => ['Piątek trzynastego', 'Usterka złapana na stronie w piątek trzynastego.', 'Wraca w każdy piątek trzynastego.']
    ];

    // Easter Sunday of a year (the Gregorian computus), as "Y-m-d"
    function safeguardEaster($year)
    {
        $a = $year % 19; $b = intdiv($year, 100); $c = $year % 100;
        $d = intdiv($b, 4); $e = $b % 4; $f = intdiv($b + 8, 25);
        $g = intdiv($b - $f + 1, 3); $h = (19 * $a + $b - $d - $g + 15) % 30;
        $i = intdiv($c, 4); $k = $c % 4; $l = (32 + 2 * $e + 2 * $i - $h - $k) % 7;
        $m = intdiv($a + 11 * $h + 22 * $l, 451);
        $month = intdiv($h + $l - 7 * $m + 114, 31);
        $day = ($h + $l - 7 * $m + 114) % 31 + 1;

        return sprintf('%04d-%02d-%02d', $year, $month, $day);
    }

    // the keys of SAFEGUARD_SEASONAL whose time a day is (a DateTimeImmutable),
    // the same as holidays() in js/safeguard.js
    function safeguardHolidays($date)
    {
        $month = (int)$date->format('n');
        $day = (int)$date->format('j');
        $list = [];
        if (($month === 12 && $day >= 20) || ($month === 1 && $day <= 6))
            $list[] = 'xmas';
        if (($month === 12 && $day === 31) || ($month === 1 && $day === 1))
            $list[] = 'newyear';
        $easter = new DateTimeImmutable(safeguardEaster((int)$date->format('Y')), $date->getTimezone());
        $days = (int)round(($date->setTime(0, 0)->getTimestamp() - $easter->getTimestamp()) / 86400);
        if ($days >= -7 && $days <= 1)
            $list[] = 'easter';
        if ($month === 4 && $day === 1)
            $list[] = 'aprilfools';
        if ($month === 10 && $day === 13)
            $list[] = 'birthday';
        if ($month === 10 && $day === 31)
            $list[] = 'halloween';
        if ($day === 13 && $date->format('N') === '5')
            $list[] = 'friday13';

        return $list;
    }

    // in how many days a seasonal find's time comes back from a day: 0 while
    // it lasts (the longest wait, for a Friday the 13th, is 14 months)
    function safeguardHolidayIn($key, $date)
    {
        $date = $date->setTime(12, 0);
        for ($in = 0; $in <= 430; $in++)
            if (in_array($key, safeguardHolidays($date->modify('+' . $in . ' days')), true))
                return $in;

        return null;
    }

    // "trwa teraz", "wraca jutro", "wraca za 19 dni"
    function safeguardHolidayWhen($in)
    {
        if ($in === null)
            return '';
        if ($in === 0)
            return 'trwa teraz';
        if ($in === 1)
            return 'wraca jutro';

        return 'wraca za ' . $in . ' dni';
    }

    // the oldest time a find from a browser may carry; older (or later than
    // now) is a clock that is wrong, and the find counts from now
    const SAFEGUARD_FIND_DAYS = 365;

    // the account's finds, key => time, in the order of the register, the
    // seasonal ones last
    function safeguardFinds($id)
    {
        $kept = readData('finds')[(string)$id] ?? [];
        $finds = [];
        foreach (array_keys(SAFEGUARD_FINDS + SAFEGUARD_SEASONAL) as $key)
            if (is_array($kept) && isset($kept[$key]) && is_int($kept[$key]))
                $finds[$key] = $kept[$key];

        return $finds;
    }

    // Notes the finds of a browser, key => time, for the account; a find it has
    // already keeps its first time. Unknown keys are left out. Returns all its
    // finds, or null when they could not be saved.
    function addSafeguardFinds($id, $found, $now)
    {
        $new = [];
        foreach (is_array($found) ? $found : [] as $key => $time) {
            if (!isset(SAFEGUARD_FINDS[$key]) && !isset(SAFEGUARD_SEASONAL[$key]))
                continue;
            $time = is_numeric($time) ? (int)$time : $now;
            $new[$key] = $time > $now || $time < $now - SAFEGUARD_FIND_DAYS * 86400 ? $now : $time;
        }
        if (!$new)
            return safeguardFinds($id);

        $saved = updateData('finds', function (&$all) use ($id, $new) {
            $kept = $all[(string)$id] ?? [];
            if (!is_array($kept))
                $kept = [];
            foreach ($new as $key => $time)
                if (!isset($kept[$key]) || !is_int($kept[$key]) || $time < $kept[$key])
                    $kept[$key] = $time;
            $all[(string)$id] = $kept;
        });

        return $saved ? safeguardFinds($id) : null;
    }

    // ---- The hunters' ranking ----------------------------------------------------
    // /lowcy/ ranks the accounts by their finds, the seasonal ones apart as in
    // the count, the one that got to its number first ahead. The panel's
    // admins are listed where they would stand, without a place: they know
    // where the eggs are, so they take no place from the others, nor the first
    // find of an egg. A hunter's name and avatar are kept with its finds in
    // inc/data/hunters.json, ID => ['name', 'avatar', 'hidden' => true once it
    // left the ranking in its profile], as the logins keep only the latest
    // LOGIN_LOG_SIZE accounts.

    // keeps the name and avatar of the logged-in account for the ranking,
    // written only when they changed; false when they could not be saved
    function noteHunter($user)
    {
        $id = (string)$user['id'];
        $kept = readData('hunters')[$id] ?? [];
        if (($kept['name'] ?? null) === (string)$user['name'] && ($kept['avatar'] ?? null) === (string)$user['avatar'])
            return true;

        return updateData('hunters', function (&$all) use ($id, $user) {
            $all[$id] = ['name' => (string)$user['name'], 'avatar' => (string)$user['avatar']] + (is_array($all[$id] ?? null) ? $all[$id] : []);
        });
    }

    function hunterHidden($id)
    {
        return !empty(readData('hunters')[(string)$id]['hidden']);
    }

    // takes the account out of the ranking, or puts it back
    function setHunterHidden($user, $hidden)
    {
        return updateData('hunters', function (&$all) use ($user, $hidden) {
            $id = (string)$user['id'];
            $entry = ['name' => (string)$user['name'], 'avatar' => (string)$user['avatar']] + (is_array($all[$id] ?? null) ? $all[$id] : []);
            if ($hidden)
                $entry['hidden'] = true;
            else
                unset($entry['hidden']);
            $all[$id] = $entry;
        });
    }

    // The ranking, best first, as ['id', 'name', 'avatar', 'badge' (roleBadge(),
    // null when unknown), 'finds' (key => time, as safeguardFinds()), 'count' of
    // the register's finds, 'reached' (when it got to that count), 'admin',
    // 'place' (null for an admin)]. The accounts that left it are not there.
    function safeguardRanking()
    {
        $hunters = readData('hunters');
        $logins = readData('logins');
        $roles = readData('roles');
        $rows = [];
        foreach (array_keys(readData('finds')) as $id) {
            $id = (string)$id;
            $finds = safeguardFinds($id);
            if (!$finds || !empty($hunters[$id]['hidden']))
                continue;
            $counted = array_intersect_key($finds, SAFEGUARD_FINDS);
            // the logins have the latest name, the hunters the one of its last find
            $known = $logins[$id] ?? $hunters[$id] ?? [];
            $rows[] = [
                'id' => $id,
                'name' => (string)($known['name'] ?? '') !== '' ? (string)$known['name'] : 'Nieznany łowca',
                'avatar' => (string)($known['avatar'] ?? ''),
                'badge' => isset($roles[$id]['roles']) ? roleBadge($roles[$id]['roles']) : null,
                'finds' => $finds,
                'count' => count($counted),
                'reached' => $counted ? max($counted) : min($finds),
                'admin' => realPanelAdminId($id),
                'place' => null
            ];
        }

        usort($rows, function ($a, $b) {
            return [$b['count'], $a['reached'], $a['id']] <=> [$a['count'], $b['reached'], $b['id']];
        });
        $place = 0;
        foreach ($rows as &$row)
            if (!$row['admin'])
                $row['place'] = ++$place;
        unset($row);

        return $rows;
    }

    // Every find of the register and the seasonal ones, key => ['hunters' =>
    // how many of the ranking made it, 'first' => the row of the one who made
    // it first, or null, 'time' => when, 'all' => [['row', 'time'], ...] of
    // everyone who made it, the first first]; the admins are left out of all.
    function safeguardFindStats($ranking)
    {
        $stats = [];
        foreach (array_keys(SAFEGUARD_FINDS + SAFEGUARD_SEASONAL) as $key)
            $stats[$key] = ['hunters' => 0, 'first' => null, 'time' => null, 'all' => []];
        foreach ($ranking as $row) {
            if ($row['admin'])
                continue;
            foreach ($row['finds'] as $key => $time)
                $stats[$key]['all'][] = ['row' => $row, 'time' => $time];
        }
        foreach ($stats as &$stat) {
            usort($stat['all'], function ($a, $b) {
                return [$a['time'], $a['row']['id']] <=> [$b['time'], $b['row']['id']];
            });
            $stat['hunters'] = count($stat['all']);
            if ($stat['all']) {
                $stat['first'] = $stat['all'][0]['row'];
                $stat['time'] = $stat['all'][0]['time'];
            }
        }
        unset($stat);

        return $stats;
    }
