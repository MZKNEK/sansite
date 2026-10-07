<?php
    // inc/gallery.php: safe paths, name validation, free names and Range parsing.

    test('resolvePath stays inside the base', function () {
        $base = tempDir();
        mkdir($base . '/sub');
        file_put_contents($base . '/f.png', 'x');
        file_put_contents($base . '/sub/g.png', 'x');
        file_put_contents($base . '/.hidden', 'x');
        file_put_contents($base . '/index.php', 'x');
        $real = str_replace('\\', '/', realpath($base));

        assertSame([$real . '/f.png', 'f.png'], resolvePath($real, 'f.png', false));
        assertSame([$real, ''], resolvePath($real, '', true));
        assertSame([$real . '/sub', 'sub'], resolvePath($real, 'sub', true));
        assertNull(resolvePath($real, 'sub', false), 'a folder asked for as a file');
        assertNull(resolvePath($real, 'f.png', true), 'a file asked for as a folder');
        assertNull(resolvePath($real, 'missing.png', false));
        assertNull(resolvePath($real, '.hidden', false), 'a hidden file');
        assertNull(resolvePath($real, 'index.php', false), 'the gallery script itself');

        // a file just outside the base must not be reachable
        $outside = dirname($real) . '/sanakan-outside-' . bin2hex(random_bytes(4)) . '.txt';
        file_put_contents($outside, 'x');
        assertNull(resolvePath($real, '../' . basename($outside), false), 'a ".." escape');
        @unlink($outside);
    });

    test('nameError refuses bad names', function () {
        assertSame('Nazwa nie może być pusta.', nameError('', false));
        assertSame('Nazwa nie może być pusta.', nameError('..', false));
        assertSame('Nazwa nie może zaczynać się od kropki.', nameError('.x', false));
        foreach (['a/b', 'a\\b', 'a:b', 'a?b', "a\0b"] as $bad)
            assertTrue(nameError($bad, false) !== null, $bad);
        assertNull(nameError('folder', false));
        assertNull(nameError('plik.png', true));
        assertNull(nameError('film.webm', true));
        assertNull(nameError('zdjęcie.heic', true));
        assertTrue(nameError('notatka.txt', true) !== null, 'a file that is not a picture or film');
        assertTrue(nameError(str_repeat('a', MAX_NAME_LENGTH + 1), false) !== null, 'too long');
        assertNull(nameError(str_repeat('a', MAX_NAME_LENGTH), false));
    });

    test('freeName adds a number when the name is taken', function () {
        $dir = tempDir();
        file_put_contents($dir . '/a.png', 'x');
        file_put_contents($dir . '/folder', 'x');
        assertSame('b.png', freeName($dir, 'b.png'));
        assertSame('a (2).png', freeName($dir, 'a.png'));
        file_put_contents($dir . '/a (2).png', 'x');
        assertSame('a (3).png', freeName($dir, 'a.png'));
        assertSame('folder (2)', freeName($dir, 'folder'));
    });

    test('path membership helpers', function () {
        assertTrue(inFolder('users/1-x/a.png', 'users/1-x'));
        assertTrue(inFolder('users/1-x', 'users/1-x'));
        assertFalse(inFolder('users/1-x2/a.png', 'users/1-x'), 'a lookalike folder');
        assertTrue(inUsersDir('users/1-x/a.png'));
        assertTrue(inUsersDir('users'));
        assertFalse(inUsersDir('other'));
        assertTrue(inPrivateDir('private/a.png'));
        assertFalse(inPrivateDir('privatex/a.png'));
        assertTrue(inUserFolder('users/123-x/a.png', '123'));
        assertTrue(inUserFolder('users/123/a.png', '123'));
        assertFalse(inUserFolder('users/1234/a.png', '123'), 'a longer id');
    });

    test('iniBytes reads the php.ini shorthand', function () {
        assertSame(512, iniBytes('512'));
        assertSame(524288, iniBytes('512K'));
        assertSame(8388608, iniBytes('8M'));
        assertSame(1073741824, iniBytes('1G'));
        assertSame(2621440, iniBytes('2.5M'));
        assertSame(0, iniBytes(''));
    });

    test('mediaRange reads one byte range', function () {
        assertNull(mediaRange('', 1000), 'no header');
        assertNull(mediaRange('items=0-1', 1000), 'a different unit');
        assertNull(mediaRange('bytes=0-1, 3-4', 1000), 'several ranges fall back to the whole file');
        assertNull(mediaRange('bytes=abc', 1000), 'not a range');

        assertSame([0, 99], mediaRange('bytes=0-99', 1000));
        assertSame([100, 999], mediaRange('bytes=100-', 1000));
        assertSame([900, 999], mediaRange('bytes=-100', 1000));
        assertSame([0, 999], mediaRange('bytes=0-5000', 1000), 'an end past the file is clamped');
        assertSame([0, 0], mediaRange('bytes=0-0', 1000));

        assertFalse(mediaRange('bytes=1000-', 1000), 'a start at the end');
        assertFalse(mediaRange('bytes=200-100', 1000), 'a backwards range');
        assertFalse(mediaRange('bytes=-0', 1000), 'no length');
        assertFalse(mediaRange('bytes=0-', 0), 'an empty file');
    });
