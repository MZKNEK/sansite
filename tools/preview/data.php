<?php
    // The made-up data of the local preview (tools/preview.sh), shared by its
    // settings, its bot API and its first fill of the data folder: the accounts
    // one can log in as, the bot's commands (now and a week ago, so the history
    // of the changes has something in it), its moderator commands and versions.
    return [
        // ID => [name, role on the bot's server (null: none), what it shows]
        'accounts' => [
            '100000000000000001' => ['Dev', 'dev', 'panel, cała galeria, API, prywatne polecenia'],
            '100000000000000002' => ['Admin', 'admin', 'API, prywatne polecenia, własny folder w galerii'],
            '100000000000000003' => ['Moderator', 'moderator', 'własny folder w galerii'],
            '100000000000000004' => ['Użytkownik', 'user', 'własny folder w galerii'],
            '100000000000000005' => ['Gość', null, 'bez roli: tylko profil i prośby o dostęp'],
        ],
        // the panel and the whole gallery
        'admin' => '100000000000000001',

        'prefix' => '.',
        'commands' => [
            ['Ogólne', '', [
                ['daily', 'Odbiera codzienną nagrodę.', ['daily', 'dzienna'], [], ''],
                ['profil', 'Pokazuje profil użytkownika.', ['profil', 'p'], [['name' => 'użytkownik', 'description' => 'użytkownik (opcjonalnie)']], '@Sniku'],
                ['ranking', 'Pokazuje ranking serwera.', ['ranking', 'top'], [['name' => 'typ', 'description' => 'typ rankingu: poziom, karty, pieniądze']], 'karty'],
                ['pomoc', 'Lista poleceń albo opis jednego z nich.', ['pomoc', 'help', 'h'], [['name' => 'polecenie', 'description' => 'nazwa polecenia (opcjonalnie)']], 'daily'],
            ]],
            ['PocketWaifu', 'pw ', [
                ['karta', 'Pokazuje kartę z kolekcji.', ['karta', 'k'], [['name' => 'id', 'description' => 'WID karty']], '12345'],
                ['wymiana', 'Proponuje wymianę kart z innym graczem.', ['wymiana', 'trade'], [['name' => 'gracz', 'description' => 'gracz'], ['name' => 'karty', 'description' => 'WID kart, oddzielone spacją']], '@Sniku 123 456'],
                ['skrzynia', 'Otwiera skrzynię z kartami.', ['skrzynia', 'box'], [['name' => 'ile', 'description' => 'ile skrzyń, domyślnie 1']], '3'],
                ['wyprawa', 'Wysyła kartę na wyprawę po przedmioty.', ['wyprawa', 'exp'], [['name' => 'id', 'description' => 'WID karty'], ['name' => 'cel', 'description' => 'cel wyprawy']], '12345 las'],
            ]],
            ['Shinden', '', [
                ['anime', 'Szuka anime na Shindenie.', ['anime', 'a'], [['name' => 'tytuł', 'description' => 'tytuł']], 'Blame!'],
                ['manga', 'Szuka mangi na Shindenie.', ['manga', 'm'], [['name' => 'tytuł', 'description' => 'tytuł']], 'Blame!'],
                ['postać', 'Szuka postaci na Shindenie i pokazuje jej kartę.', ['postać', 'char'], [['name' => 'imię', 'description' => 'imię postaci']], 'Sanakan'],
            ]],
            ['Zabawa', '', [
                ['kostka', 'Rzuca kostką.', ['kostka', 'dice'], [['name' => 'ścianki', 'description' => 'liczba ścianek, domyślnie 6']], '20'],
                ['moneta', 'Rzuca monetą o zakład.', ['moneta', 'coin'], [['name' => 'stawka', 'description' => 'stawka']], '100'],
            ]],
        ],
        // a week ago: no expedition yet, and the character search said less
        'commandsBefore' => [
            'drop' => ['wyprawa'],
            'describe' => ['postać' => 'Szuka postaci na Shindenie.'],
        ],
        'private' => [
            ['Moderacja', 'mod ', [
                ['ban', 'Banuje użytkownika.', ['ban'], [['name' => 'użytkownik', 'description' => 'użytkownik'], ['name' => 'powód', 'description' => 'powód']], '@spam reklamy'],
                ['wycisz', 'Wycisza użytkownika na czas.', ['wycisz', 'mute'], [['name' => 'użytkownik', 'description' => 'użytkownik'], ['name' => 'czas', 'description' => 'czas w minutach']], '@spam 60'],
            ]],
        ],

        // oldest first, with how many days ago each started; the last one runs now
        'versions' => [['1.4.10.17', 40], ['1.4.10.18', 26], ['1.4.10.19', 11], ['1.4.10.20', 3]],
    ];
