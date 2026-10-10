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

        sessionFor('sanakan-see-uploader', ['id' => '556', 'name' => 'U', 'avatar' => 'a']);
        $baseU = tempDir();
        $own = ownFolder($baseU);
        assertSame('users/556-U', $own);
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
        sessionFor('sanakan-own-1', ['id' => '556', 'name' => 'Jan Kowalski', 'avatar' => 'a']);
        assertSame('users/556-Jan Kowalski', ownFolder($base));
        assertTrue(is_dir($base . '/users/556-Jan Kowalski'));
        sessionEnd();

        $base2 = tempDir();
        mkdir($base2 . '/users/556-Stary', 0700, true);
        sessionFor('sanakan-own-2', ['id' => '556', 'name' => 'Nowy', 'avatar' => 'a']);
        assertSame('users/556-Nowy', ownFolder($base2), 'the folder follows the nick');
        assertTrue(is_dir($base2 . '/users/556-Nowy'));
        assertFalse(is_dir($base2 . '/users/556-Stary'));
        sessionEnd();
    });

    test('ownFolder keeps a bad nickname out of the path', function () {
        $base = tempDir();
        sessionFor('sanakan-own-3', ['id' => '556', 'name' => 'a/b:c', 'avatar' => 'a']);
        assertSame('users/556-abc', ownFolder($base));
        assertTrue(is_dir($base . '/users/556-abc'));
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

    test('a shared private folder is seen by whoever has the link', function () {
        // without opening the link, nothing of private/ is seen
        $before = tempDir();
        sessionFor('sanakan-share-private-a', ['id' => '606', 'name' => 'S', 'avatar' => 'a']);
        assertFalse(galleryCanSee($before, 'private/a.png'));
        sessionEnd();

        $base = tempDir();
        mkdir($base . '/private/sub', 0700, true);
        file_put_contents($base . '/private/a.png', 'x');
        file_put_contents($base . '/private/sub/b.png', 'x');

        sessionFor('sanakan-share-private-b', ['id' => '606', 'name' => 'S', 'avatar' => 'a']);
        $token = createShare('private', 7, siteUser());
        assertSame('private', openShare($base, $token));
        assertTrue(galleryCanSee($base, 'private'), 'the shared folder');
        assertTrue(galleryCanSee($base, 'private/a.png'), 'its files');
        assertTrue(galleryCanSee($base, 'private/sub'), 'and subfolders');
        assertTrue(galleryCanSee($base, 'private/sub/b.png'));
        sessionEnd();

        // a link for a subfolder opens only that one, not the whole private folder
        $base2 = tempDir();
        mkdir($base2 . '/private/sub', 0700, true);
        file_put_contents($base2 . '/private/a.png', 'x');
        file_put_contents($base2 . '/private/sub/b.png', 'x');
        sessionFor('sanakan-share-private-c', ['id' => '606', 'name' => 'S', 'avatar' => 'a']);
        $sub = createShare('private/sub', 7, siteUser());
        assertSame('private/sub', openShare($base2, $sub));
        assertTrue(galleryCanSee($base2, 'private/sub/b.png'));
        assertFalse(galleryCanSee($base2, 'private/a.png'), 'the rest of private stays out');
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
