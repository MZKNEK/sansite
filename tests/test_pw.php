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

    test('pwParseVariants reads the styles of the frames from the bot\'s code', function () {
        $code = "class X {\n    public static int GetCardVariantsCount(this Card card)\n    {\n        return card.Quality switch\n"
            . "        {\n            Quality.Delta => 8,\n            Quality.Eta => 18,\n            _ => 0\n        };\n    }\n"
            . "    Quality.Omega => 99\n}";
        assertSame(['Delta' => 8, 'Eta' => 18], pwParseVariants($code));
        assertNull(pwParseVariants('no such method'));
    });
