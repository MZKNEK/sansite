<?php
    // inc/profilator.php: the profile of the logged-in account for Profilator.
    require_once __DIR__ . '/../inc/profilator.php';

    test('profilatorData keeps the answer of the bot a while', function () {
        writeData('profilator', [
            '123456789012345678' => ['time' => 1000, 'data' => ['code' => 'AQADJxke']],
            '123456789012345679' => ['time' => 1000, 'data' => false]
        ]);

        assertSame(['code' => 'AQADJxke'], profilatorData('123456789012345678', 1000 + PROFILATOR_TTL - 1));
        assertSame(false, profilatorData('123456789012345679', 1000 + PROFILATOR_TTL - 1), 'no profile is kept too');
        assertNull(profilatorData('123456789012345678', 1000 + PROFILATOR_TTL), 'too old, and without the site\'s key the bot is not asked');
    });

    test('profilatorView keeps only what the page draws, and links it may load', function () {
        $view = profilatorView([
            'code' => 'AQADJxke', 'nick' => 'Gracz', 'rank' => 'Użytkownik', 'color' => '#E86262',
            'avatar' => 'https://cdn.discordapp.com/avatars/1/a.png',
            'waifu' => 'https://evil.example/w.webp',
            'gallery' => ['https://cdn2.shinden.eu/profile/5.webp', 'javascript:alert(1)', 'https://cdn2.shinden.eu.evil/x.webp'],
            'level' => 23, 'sc' => '42000', 'karma' => 'x',
            'rarities' => ['SS' => 2, 'Q' => 9],
            'anime' => ['completed' => 210, 'rating' => 8.1, 'secret' => 'no'],
            'manga' => null,
            'extra' => 'dropped'
        ]);

        assertSame('https://cdn.discordapp.com/avatars/1/a.png', $view['avatar']);
        assertNull($view['waifu'], 'a picture from elsewhere');
        assertSame(['https://cdn2.shinden.eu/profile/5.webp', null, null], $view['gallery'], 'the places stay, the bad links go');
        assertSame('#E86262', $view['color']);
        assertSame(23, $view['level']);
        assertSame(42000, $view['sc']);
        assertSame(0, $view['karma']);
        assertSame(['SSS' => 0, 'SS' => 2, 'S' => 0, 'A' => 0, 'B' => 0, 'C' => 0, 'D' => 0, 'E' => 0], $view['rarities']);
        assertSame(210, $view['anime']['completed']);
        assertSame(8.1, $view['anime']['rating']);
        assertFalse(isset($view['anime']['secret']));
        assertNull($view['manga']);
        assertFalse(isset($view['extra']));
    });

    test('profilatorView gives a grey without a colour of a role', function () {
        assertSame('#546e7a', profilatorView(['color' => 'red'])['color']);
        assertSame([], profilatorView([])['gallery']);
    });
