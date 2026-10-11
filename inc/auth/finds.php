<?php
    // The Safeguard's register: the easter eggs of the site an account has
    // found, shown in its profile (/account/). Part of the login, loaded by
    // inc/auth.php.

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
        'offline'   => ['Odcięty od sieci', 'Strona widziana bez połączenia z siecią.', 'Odetnij się od sieci i spróbuj wrócić.'],
        'morse'     => ['Sygnał zwrotny', 'Słowo wystukane Morse\'em na motcie strony głównej i odpowiedź Safeguarda.', 'Motto na górze strony głównej nie tylko nadaje. Wołanie o pomoc to dobry początek.'],
        'idle'      => ['Czuwanie', 'Strona główna zasnęła w bezruchu, a Sanakan czuwała dalej.', 'Zostaw stronę główną na dłużej w spokoju.'],
        'regen'     => ['Regeneracja', 'Trzy strzały z Emitera pod rząd rozłupały logo, a Sanakan się zregenerowała.', 'Jeden strzał z Emitera to za mało, żeby zranić Safeguarda.']
    ];

    // The finds of a time of the year, the same way (js/safeguard.js, js/home.js),
    // listed apart and left out of the count, as the rest of the year nobody
    // can make them; the hint says when they come back.
    const SAFEGUARD_SEASONAL = [
        'xmas'   => ['Święta w Megastrukturze', 'Czapka Mikołaja strącona z głowy Sanakan. Safeguard nie obchodzi świąt.', 'Wraca co roku od 20 grudnia do Trzech Króli.'],
        'easter' => ['Pisanki z Megastruktury', 'Pięć pisanek rozbitych na pięciu stronach w czasie Wielkanocy.', 'Wraca co roku od Niedzieli Palmowej do Poniedziałku Wielkanocnego.']
    ];

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
