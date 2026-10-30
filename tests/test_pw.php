<?php
    // inc/pw.php: the mirror of the pictures of the bot's cards for the croppers.
    require_once __DIR__ . '/../inc/pw.php';

    test('pwGitHash is the hash git gives a file', function () {
        // git hash-object of "abc"
        assertSame('f2ba8f84ab5c1bce84a7b441cb1959cfc7093b7f', pwGitHash('abc'));
    });

    test('pwLocalName keeps the pictures of the folder and nothing outside it', function () {
        assertSame('SSS.png', pwLocalName('src/Pictures/PW/SSS.png'));
        assertSame('CG/Delta/Border2.png', pwLocalName('src/Pictures/PW/CG/Delta/Border2.png'));
        assertSame('stars/Full/4_4.png', pwLocalName('src/Pictures/PW/stars/Full/4_4.png'));
        assertNull(pwLocalName('src/Pictures/Other/a.png'), 'another folder');
        assertNull(pwLocalName('src/Pictures/PW/notes.txt'), 'not a picture');
        assertNull(pwLocalName('src/Pictures/PW/../secret.png'), 'out of the folder');
        assertNull(pwLocalName('src/Pictures/PW/.hidden.png'), 'a dot file');
        assertNull(pwLocalName('src/Pictures/PW/a\b.png'), 'a backslash');
    });

    test('pwLocalName puts the pictures of the profile under np/', function () {
        assertSame('np/pbase.png', pwLocalName('src/Pictures/np/pbase.png'));
        assertSame('np/ab/BaseLv/25.png', pwLocalName('src/Pictures/np/ab/BaseLv/25.png'));
        assertSame('np/statsAnime.png', pwLocalName('src/Pictures/statsAnime.png'));
        assertSame('np/statsManga.png', pwLocalName('src/Pictures/statsManga.png'));
        assertNull(pwLocalName('src/Pictures/coin0.png'), 'another picture of the folder above');
        assertNull(pwLocalName('src/Pictures/np/../PW/SSS.png'), 'out of the folder');
        assertNull(pwLocalName('src/Pictures/PW/np/pbase.png'), 'a folder of the cards named like the profile\'s');
    });

    test('pwSourcePath finds every mirrored file in the repository again', function () {
        foreach (['src/Pictures/PW/SSS.png', 'src/Pictures/PW/CG/Delta/Border2.png', 'src/Pictures/np/pbase.png',
            'src/Pictures/np/ab/Gold.png', 'src/Pictures/statsAnime.png', 'src/Pictures/statsManga.png'] as $path)
            assertSame($path, pwSourcePath(pwLocalName($path)));
    });

    test('pwParseVariants reads the styles of the frames from the bot\'s code', function () {
        $code = "class X {\n    public static int GetCardVariantsCount(this Card card)\n    {\n        return card.Quality switch\n"
            . "        {\n            Quality.Delta => 8,\n            Quality.Eta => 18,\n            _ => 0\n        };\n    }\n"
            . "    Quality.Omega => 99\n}";
        assertSame(['Delta' => 8, 'Eta' => 18], pwParseVariants($code));
        assertNull(pwParseVariants('no such method'));
    });
