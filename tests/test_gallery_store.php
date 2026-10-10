<?php
    // inc/auth.php and inc/gallery.php: the per-request data cache, the links of
    // an account's folder, the hashes/duplicates and the trash.

    test('readData caches and every write drops the entry', function () {
        writeData('demo', ['v' => 1]);
        assertSame(['v' => 1], readData('demo'));
        // a change on disk is not seen while the value is cached
        file_put_contents(dataDir() . '/demo.json', json_encode(['v' => 2]));
        assertSame(['v' => 1], readData('demo'));
        forgetData('demo');
        assertSame(['v' => 2], readData('demo'));
        assertSame([], readData('does-not-exist'));
    });

    test('updateDataFile keeps what another request wrote', function () {
        writeData('counter', ['n' => 1]);
        updateDataFile('counter.json', function ($data) { $data['n']++; return $data; });
        assertSame(['n' => 2], readData('counter'));
    });

    test('publicRel and privateRel swap the id for a token', function () {
        $base = tempDir();
        mkdir($base . '/users/123-Sniku', 0700, true);
        file_put_contents($base . '/users/123-Sniku/a.png', 'x');
        writeData('user-folders', ['123' => 'abcdef0123456789']);

        assertSame('u/abcdef0123456789/a.png', publicRel('users/123-Sniku/a.png'));
        assertSame('users/123-Sniku/a.png', privateRel($base, 'u/abcdef0123456789/a.png'));
        assertSame('u/abcdef0123456789/a.png', userLinkRel('/i/u/abcdef0123456789/a.png'));
        assertNull(userLinkRel('/i/not-a-link.png'));
        // resolvePath goes through privateRel, so a u/ link reaches the file
        $file = resolvePath($base, 'u/abcdef0123456789/a.png', false);
        assertSame('users/123-Sniku/a.png', $file[1]);
    });

    test('uploadedLinks gives the link of the file and its folder, by token', function () {
        writeData('user-folders', ['123' => 'abcdef0123456789']);
        $saved = $_SERVER['SCRIPT_NAME'] ?? null;
        $savedFile = $_SERVER['SCRIPT_FILENAME'] ?? null;
        unset($_SERVER['SCRIPT_FILENAME']);
        $_SERVER['SCRIPT_NAME'] = '/i/index.php';
        try {
            // a card of the croppers, in the folder of an account: the ID never in a link
            assertSame([
                'url' => '/i/u/abcdef0123456789/Skalpelator/karta%201.png',
                'folder' => '/i/?p=u%2Fabcdef0123456789%2FSkalpelator'
            ], uploadedLinks('users/123-Sniku/' . CROPPER_DIR . '/karta 1.png'));
            // a file in the top folder has the gallery itself as its folder
            assertSame(['url' => '/i/a.png', 'folder' => '/i/'], uploadedLinks('a.png'));
        } finally {
            $_SERVER['SCRIPT_NAME'] = $saved;
            if ($savedFile !== null)
                $_SERVER['SCRIPT_FILENAME'] = $savedFile;
        }
    });

    test('hashes follow a move and the duplicate check walks a folder', function () {
        $base = tempDir();
        mkdir($base . '/sub');
        $content = 'same-content';
        file_put_contents($base . '/a.png', $content);
        file_put_contents($base . '/sub/b.png', $content);
        $hash = hash('sha256', $content);
        $size = strlen($content);

        assertSame(['i/a.png', 'i/sub/b.png'], findDuplicates($base, $size, $hash));
        assertSame(['i/sub/b.png'], findDuplicates($base, $size, $hash, 'sub'), 'only the given folder');
        assertSame([], findDuplicates($base, 3, hash('sha256', 'nope')));

        moveHashes('a.png', 'c.png');
        $moved = readData('hashes');
        assertTrue(isset($moved['c.png']));
        assertFalse(isset($moved['a.png']));
        dropHashes('c.png');
        assertFalse(isset(readData('hashes')['c.png']));
    });

    test('trash moves a file and brings it back', function () {
        $base = tempDir();
        file_put_contents($base . '/a.png', 'picture');
        assertTrue(moveToTrash($base . '/a.png', 'a.png'));
        assertFalse(is_file($base . '/a.png'));

        $items = trashItems();
        $id = array_key_first($items);
        assertSame('a.png', $items[$id]['name']);
        assertSame('a.png', $items[$id]['from']);
        assertFalse($items[$id]['folder']);
        // the file is given out by its trash id
        assertSame([trashDir() . '/' . $id . '/a.png', ''], trashFile($id, ''));

        assertSame('a.png', restoreFromTrash($base, $id));
        assertTrue(is_file($base . '/a.png'));
        assertSame([], trashItems());
    });

    test('a deleted folder keeps its pictures and films', function () {
        $base = tempDir();
        mkdir($base . '/album');
        file_put_contents($base . '/album/x.png', 'x');
        file_put_contents($base . '/album/y.webm', 'y');
        file_put_contents($base . '/album/note.txt', 'n');
        assertTrue(moveToTrash($base . '/album', 'album'));
        $id = array_key_first(trashItems());
        list($files, $count) = trashFolderMedia($id, 10);
        assertSame(2, $count, 'only the pictures and the films');
        assertSame('x.png', $files[0][0]);
        assertSame('y.webm', $files[1][0]);
    });

    test('purgeTrash drops what is older than the keep days', function () {
        $base = tempDir();
        file_put_contents($base . '/old.png', 'x');
        moveToTrash($base . '/old.png', 'old.png');
        $id = array_key_first(trashItems());
        $items = readData('trash');
        $items[$id]['deleted'] = time() - (TRASH_DAYS + 1) * 86400;
        writeData('trash', $items);
        purgeTrash();
        assertSame([], trashItems());
        assertFalse(is_dir(trashDir() . '/' . $id));
    });

    test('historyEntries drops the oldest past the cap', function () {
        $line = json_encode(['time' => time(), 'id' => '', 'name' => '', 'action' => 'x', 'text' => str_repeat('a', 600)]) . "\n";
        file_put_contents(historyFile(), str_repeat($line, HISTORY_KEEP + 100));
        clearstatcache();

        addHistory('y', 'nowy wpis');
        assertSame(HISTORY_KEEP, count(historyEntries(HISTORY_KEEP)), 'trimmed to the cap');
    });

    test('pruneThumbs drops the ones nobody looked at', function () {
        $dir = thumbsDir();
        if (!is_dir($dir))
            mkdir($dir, 0775, true);
        $old = $dir . '/old.webp';
        $new = $dir . '/new.webp';
        file_put_contents($old, 'x');
        file_put_contents($new, 'x');
        @touch($old, time() - (THUMB_KEEP_DAYS + 1) * 86400);

        assertSame(1, pruneThumbs());
        assertFalse(is_file($old));
        assertTrue(is_file($new));
    });
