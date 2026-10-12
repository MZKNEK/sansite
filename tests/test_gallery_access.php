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

        writeData('roles', ['556' => ['roles' => ['onGuild' => true, 'user' => true], 'checked' => time()]]);
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
        writeData('roles', ['556' => ['roles' => ['onGuild' => true, 'user' => true], 'checked' => time()]]);
        sessionFor('sanakan-own-1', ['id' => '556', 'name' => 'Jan Kowalski', 'avatar' => 'a']);
        assertSame('users/556-Jan Kowalski', ownFolder($base));
        assertTrue(is_dir($base . '/users/556-Jan Kowalski'));
        sessionEnd();

        $base2 = tempDir();
        mkdir($base2 . '/users/556-Stary', 0700, true);
        writeData('roles', ['556' => ['roles' => ['onGuild' => true, 'user' => true], 'checked' => time()]]);
        sessionFor('sanakan-own-2', ['id' => '556', 'name' => 'Nowy', 'avatar' => 'a']);
        assertSame('users/556-Nowy', ownFolder($base2), 'the folder follows the nick');
        assertTrue(is_dir($base2 . '/users/556-Nowy'));
        assertFalse(is_dir($base2 . '/users/556-Stary'));
        sessionEnd();
    });

    test('ownFolder keeps a bad nickname out of the path', function () {
        $base = tempDir();
        writeData('roles', ['556' => ['roles' => ['onGuild' => true, 'user' => true], 'checked' => time()]]);
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
        $roots = galleryZipRoots($dir, [[$dir . '/a.png', 'a.png'], [$dir, '']]);
        assertSame('a.png', $roots[0][1]);
        assertSame('i', $roots[1][1]);
        assertContains('index.php', $roots[1][2], 'the gallery script is left out');
    });

    // the names a ZIP of these gallery items would hold for the visitor now
    function zipNames($base, $items)
    {
        $files = [];
        $bytes = 0;
        foreach (galleryZipRoots($base, $items) as $root)
            zipCollect($root[0], $root[1], $root[2], $files, $bytes, 0, $root[3]);

        return array_column($files, 1);
    }

    test('a ZIP holds only what the visitor may see', function () {
        $base = tempDir();
        foreach (['private/sub', 'users/9-x', 'shared', 'other'] as $dir)
            mkdir($base . '/' . $dir, 0700, true);
        foreach (['a.png', 'index.php', 'private/p.png', 'private/sub/s.png', 'users/9-x/u.png', 'shared/h.png', 'other/o.png'] as $file)
            file_put_contents($base . '/' . $file, 'x');

        // an admin of the gallery without the right to the private folder
        sessionFor('sanakan-zip-admin', ['id' => '111', 'name' => 'A', 'avatar' => 'a']);
        $names = zipNames($base, [[$base, '']]);
        assertContains('i/a.png', $names);
        assertContains('i/users/9-x/u.png', $names, 'an admin gets the account folders');
        foreach ($names as $name)
            assertFalse(strpos($name, 'i/private') === 0, 'but nothing of the private folder: ' . $name);
        assertFalse(in_array('i/index.php', $names, true));
        sessionEnd();

        sessionFor('sanakan-zip-viewer', ['id' => '222', 'name' => 'V', 'avatar' => 'a']);
        $names = zipNames($base, [[$base, '']]);
        assertContains('i/a.png', $names);
        assertContains('i/other/o.png', $names);
        foreach ($names as $name)
            assertTrue(strpos($name, 'i/private') !== 0 && strpos($name, 'i/users') !== 0, 'a viewer gets neither: ' . $name);
        sessionEnd();

        sessionFor('sanakan-zip-private', ['id' => '444', 'name' => 'P', 'avatar' => 'a']);
        assertSame(['private/p.png', 'private/sub/s.png'], zipNames($base, [[$base . '/private', 'private']]),
            'whoever may see the private folder gets it whole');
        sessionEnd();
    });

    test('a ZIP by a shared link holds only the shared folder', function () {
        $base = tempDir();
        mkdir($base . '/private/sub', 0700, true);
        file_put_contents($base . '/private/p.png', 'x');
        file_put_contents($base . '/private/sub/s.png', 'x');

        sessionFor('sanakan-zip-share', ['id' => '606', 'name' => 'S', 'avatar' => 'a']);
        openShare($base, createShare('private/sub', 7, siteUser()));
        $names = zipNames($base, [[$base . '/private', 'private'], [$base . '/private/sub', 'private/sub']]);
        sessionEnd();
        assertSame(['sub/s.png'], $names);
    });

    // ZIP and GD are there in CI (.github/workflows/ci.yml): a test for them is
    // left out only on a computer without them, never there
    function needExtension($ok, $name)
    {
        if (!$ok && getenv('CI'))
            test_assert(false, 'CI has no ' . $name . ', the test of it would be left out');

        return $ok;
    }

    test('the real ZIP of a folder holds what the visitor may see', function () {
        if (!needExtension(canZip(), 'ZIP'))
            return;
        $base = tempDir();
        foreach (['private', 'users/9-x', 'sub'] as $dir)
            mkdir($base . '/' . $dir, 0700, true);
        foreach (['a.png' => 'A', 'index.php' => 'x', 'private/p.png' => 'P', 'users/9-x/u.png' => 'U', 'sub/b.png' => 'B'] as $file => $content)
            file_put_contents($base . '/' . $file, $content);

        sessionFor('sanakan-zip-real', ['id' => '111', 'name' => 'A', 'avatar' => 'a']);
        $files = [];
        $bytes = 0;
        foreach (galleryZipRoots($base, [[$base, '']]) as $root)
            zipCollect($root[0], $root[1], $root[2], $files, $bytes, 0, $root[3]);
        sessionEnd();
        $path = tempDir() . '/test.zip';
        assertTrue(writeZip($files, $path));

        $zip = new ZipArchive();
        assertTrue($zip->open($path) === true);
        $names = [];
        for ($i = 0; $i < $zip->numFiles; $i++)
            $names[] = $zip->getNameIndex($i);
        sort($names);
        assertSame(['i/a.png', 'i/sub/b.png', 'i/users/9-x/u.png'], $names, 'the private folder and the script stay out');
        assertSame('B', $zip->getFromName('i/sub/b.png'));
        assertSame(ZipArchive::CM_STORE, $zip->statName('i/a.png')['comp_method'], 'stored, not compressed');
        $zip->close();
    });

    test('a ZIP does not follow links', function () {
        $base = tempDir();
        $outside = tempDir();
        file_put_contents($outside . '/secret.png', 'x');
        file_put_contents($base . '/a.png', 'x');
        if (!@symlink($outside, $base . '/linked') || !@symlink($outside . '/secret.png', $base . '/s.png'))
            return; // links cannot be made here (Windows without the right to)

        sessionFor('sanakan-zip-links', ['id' => '111', 'name' => 'A', 'avatar' => 'a']);
        assertSame(['i/a.png'], zipNames($base, [[$base, '']]));
        sessionEnd();
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

    // one change asked of the gallery's page by an account, in a process of its
    // own, as handlePost() ends the one it runs in: the answer
    function galleryPost($id, $base, $fields)
    {
        $null = DIRECTORY_SEPARATOR === '\\' ? 'NUL' : '/dev/null';
        $process = proc_open([PHP_BINARY, __DIR__ . '/gallery-post-child.php', $id, $base, json_encode($fields)],
            [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['file', $null, 'w']], $pipes, __DIR__,
            array_merge(getenv() ?: [], ['SANAKAN_TEST_DATA' => SITE_DATA_DIR]));
        fclose($pipes[0]);
        $output = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        proc_close($process);

        return json_decode((string)$output, true) ?? ['output' => $output];
    }

    test('a gallery admin without the private folder changes nothing in it', function () {
        $base = tempDir();
        mkdir($base . '/private', 0700, true);
        file_put_contents($base . '/a.png', 'x');
        file_put_contents($base . '/private/s.png', 'x');

        $answer = galleryPost('111', $base, ['action' => 'mkdir', 'dir' => 'private', 'name' => 'new']);
        assertFalse($answer['ok'] ?? null, json_encode($answer));
        assertFalse(is_dir($base . '/private/new'), 'no folder made in it');

        $answer = galleryPost('111', $base, ['action' => 'move', 'target' => 'private', 'items' => ['a.png']]);
        assertFalse($answer['ok'] ?? null, json_encode($answer));
        assertTrue(is_file($base . '/a.png'), 'nothing moved into it');
        assertFalse(is_file($base . '/private/a.png'));

        $answer = galleryPost('111', $base, ['action' => 'upload', 'dir' => 'private']);
        assertSame('Nie masz dostępu do tego folderu.', $answer['message'] ?? null, 'nor uploaded');
        $answer = galleryPost('111', $base, ['action' => 'share', 'dir' => 'private', 'days' => '7']);
        assertSame('Nie masz dostępu do tego folderu.', $answer['message'] ?? null, 'nor shared');

        // the whole gallery but that is still its own
        $answer = galleryPost('111', $base, ['action' => 'mkdir', 'dir' => '', 'name' => 'pub']);
        assertTrue($answer['ok'] ?? null, json_encode($answer));
        assertTrue(is_dir($base . '/pub'));
        // and a gallery admin who may see the private folder changes it too
        $answer = galleryPost('445', $base, ['action' => 'mkdir', 'dir' => 'private', 'name' => 'new']);
        assertTrue($answer['ok'] ?? null, json_encode($answer));
        assertTrue(is_dir($base . '/private/new'));
    });

    test('a gallery admin brings nothing back from the trash into the private folder', function () {
        $base = tempDir();
        mkdir($base . '/private', 0700, true);
        file_put_contents($base . '/private/s.png', 'x');
        assertTrue(moveToTrash($base . '/private/s.png', 'private/s.png'));
        $id = array_key_first(trashItems());

        $answer = galleryPost('111', $base, ['action' => 'restore', 'item' => $id]);
        assertFalse($answer['ok'] ?? null, json_encode($answer));
        assertFalse(is_file($base . '/private/s.png'));
        $answer = galleryPost('111', $base, ['action' => 'trash-delete', 'item' => $id]);
        assertFalse($answer['ok'] ?? null, json_encode($answer));
        forgetData('trash');
        assertTrue(isset(trashItems()[$id]), 'nor deletes it for good');
    });

    test('the duplicate check is gone', function () {
        $base = tempDir();
        $answer = galleryPost('111', $base, ['action' => 'duplicates', 'dir' => '', 'size' => '1', 'hash' => str_repeat('0', 64)]);
        assertSame('Nieznana akcja.', $answer['message'] ?? null);
    });

    test('a link shared for the private folder only shows it', function () {
        $base = tempDir();
        mkdir($base . '/private', 0700, true);
        sessionFor('sanakan-change-share', ['id' => '111', 'name' => 'A', 'avatar' => 'a']);
        $token = createShare('private', 7, siteUser());
        assertSame('private', openShare($base, $token));
        assertTrue(galleryCanSee($base, 'private/a.png'));
        assertFalse(galleryCanChange($base, 'private/a.png'));
        assertTrue(galleryCanChange($base, 'a.png'), 'the rest of the gallery it may change');
        sessionEnd();

        writeData('roles', ['556' => ['roles' => ['onGuild' => true, 'user' => true], 'checked' => time()]]);
        // another gallery, as ownFolder() keeps the folder of a gallery for the request
        $baseU = tempDir();
        sessionFor('sanakan-change-own', ['id' => '556', 'name' => 'U', 'avatar' => 'a']);
        $own = ownFolder($baseU);
        assertTrue(galleryCanChange($baseU, $own . '/a.png'));
        assertFalse(galleryCanChange($baseU, 'a.png'), 'an account with a folder of its own only that folder');
        sessionEnd();
    });
