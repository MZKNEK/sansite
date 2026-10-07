<?php
    // inc/gallery.php with a signed-in account: what may be seen, the folder of
    // an account, the shared links and the account activity.

    test('galleryCanSee decides by the rights', function () {
        $base = tempDir();
        mkdir($base . '/private', 0700, true);
        file_put_contents($base . '/a.png', 'x');

        sessionFor('sanakan-see-admin', ['id' => '111', 'name' => 'A', 'avatar' => 'a']);
        assertTrue(galleryCanSee($base, 'a.png'));
        assertTrue(galleryCanSee($base, 'users/9-x/a.png'), 'an admin sees the account folders');
        assertFalse(galleryCanSee($base, 'private/a.png'), 'but not the private one');
        sessionEnd();

        sessionFor('sanakan-see-viewer', ['id' => '222', 'name' => 'V', 'avatar' => 'a']);
        assertTrue(galleryCanSee($base, 'a.png'));
        assertFalse(galleryCanSee($base, 'users/9-x/a.png'));
        assertFalse(galleryCanSee($base, 'private/a.png'));
        sessionEnd();

        sessionFor('sanakan-see-uploader', ['id' => '333', 'name' => 'U', 'avatar' => 'a']);
        $baseU = tempDir();
        $own = ownFolder($baseU);
        assertSame('users/333-U', $own);
        assertTrue(galleryCanSee($baseU, $own . '/a.png'), 'its own folder');
        assertFalse(galleryCanSee($baseU, 'a.png'), 'and nothing else');
        sessionEnd();

        sessionFor('sanakan-see-private', ['id' => '444', 'name' => 'P', 'avatar' => 'a']);
        assertTrue(galleryCanSee($base, 'private/a.png'));
        assertFalse(galleryCanSee($base, 'a.png'));
        sessionEnd();
    });

    test('ownFolder creates the folder and follows a new nickname', function () {
        $base = tempDir();
        sessionFor('sanakan-own-1', ['id' => '333', 'name' => 'Jan Kowalski', 'avatar' => 'a']);
        assertSame('users/333-Jan Kowalski', ownFolder($base));
        assertTrue(is_dir($base . '/users/333-Jan Kowalski'));
        sessionEnd();

        $base2 = tempDir();
        mkdir($base2 . '/users/333-Stary', 0700, true);
        sessionFor('sanakan-own-2', ['id' => '333', 'name' => 'Nowy', 'avatar' => 'a']);
        assertSame('users/333-Nowy', ownFolder($base2), 'the folder follows the nick');
        assertTrue(is_dir($base2 . '/users/333-Nowy'));
        assertFalse(is_dir($base2 . '/users/333-Stary'));
        sessionEnd();
    });

    test('ownFolder keeps a bad nickname out of the path', function () {
        $base = tempDir();
        sessionFor('sanakan-own-3', ['id' => '333', 'name' => 'a/b:c', 'avatar' => 'a']);
        assertSame('users/333-abc', ownFolder($base));
        assertTrue(is_dir($base . '/users/333-abc'));
        sessionEnd();
    });

    test('a shared link opens the folder in the session', function () {
        $base = tempDir();
        mkdir($base . '/pub', 0700, true);
        sessionFor('sanakan-share-1', ['id' => '222', 'name' => 'V', 'avatar' => 'a']);
        $token = createShare('pub', 7, siteUser());
        assertSame('pub', openShare($base, $token));
        assertSame(['pub'], sessionShares($base));
        assertNull(openShare($base, 'nope'), 'a bad token opens nothing');
        sessionEnd();
    });

    test('galleryZipRoots names the top folder i', function () {
        $dir = tempDir();
        $roots = galleryZipRoots([[$dir . '/a.png', 'a.png'], [$dir, '']]);
        assertSame('a.png', $roots[0][1]);
        assertSame('i', $roots[1][1]);
        assertContains('index.php', $roots[1][2], 'the gallery script is left out');
    });

    test('accountActivity counts what an account did', function () {
        sessionFor('sanakan-act-1', ['id' => '555', 'name' => 'T', 'avatar' => 'a']);
        addHistory('upload', 'Dodano i/a.png.');
        addHistory('delete', 'Do kosza: i/a.png, i/b.png.');
        sessionEnd();

        list($history, $gallery, $uploads) = accountActivity('555', false, 60, 12);
        assertSame(1, $gallery['upload']);
        assertSame(2, $gallery['delete'], 'the list counts each file');
        assertSame('a.png', $uploads[0]['rel']);
        assertSame(2, count($history));
    });
