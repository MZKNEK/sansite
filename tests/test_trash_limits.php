<?php
    // inc/gallery.php: what comes back from the trash into the folder of an
    // account keeps to its limits, and its trash has a budget of its own.

    // the folder of account 556 with $files ([name => bytes]) in it, as that
    // account logged in; its limit of files is $limit
    function trashAccount($files, $limit = 50)
    {
        writeData('roles', ['556' => ['roles' => ['onGuild' => true, 'user' => true], 'checked' => time()]]);
        writeData('access', ['uploadLimits' => ['556' => $limit]]);
        $base = tempDir();
        mkdir($base . '/users/556-U', 0755, true);
        foreach ($files as $name => $bytes) {
            if (!is_dir(dirname($base . '/users/556-U/' . $name)))
                mkdir(dirname($base . '/users/556-U/' . $name), 0755, true);
            file_put_contents($base . '/users/556-U/' . $name, str_repeat('x', $bytes));
        }
        sessionFor('sanakan-trash-556-' . bin2hex(random_bytes(3)), ['id' => '556', 'name' => 'U', 'avatar' => 'a']);

        return $base;
    }

    // the id of the newest trash entry
    function newestTrash()
    {
        return (string)array_key_first(trashItems());
    }

    test('upload, delete, upload, restore: the restore does not go over the limit', function () {
        $base = trashAccount(['a.png' => 10, 'b.png' => 10], 2);
        assertTrue(moveToTrash($base . '/users/556-U/a.png', 'users/556-U/a.png'));
        $id = newestTrash();
        assertSame('556', trashItems()[$id]['owner'], 'the entry knows whose it is');
        file_put_contents($base . '/users/556-U/c.png', 'x');

        assertNull(restoreFromTrash($base, $id, $error));
        sessionEnd();
        assertTrue(is_string($error) && strpos($error, '2 z 2') !== false, 'the reason says why: ' . $error);
        assertTrue(isset(readData('trash')[$id]), 'the entry stays in the trash');
        assertTrue(is_file(trashDir() . '/' . $id . '/a.png'), 'and its file too');
        assertFalse(is_file($base . '/users/556-U/a.png'));
    });

    test('a folder is restored only when all of it fits', function () {
        $base = trashAccount(['sub/a.png' => 10, 'sub/b.png' => 10, 'sub/c.png' => 10], 2);
        assertTrue(moveToTrash($base . '/users/556-U/sub', 'users/556-U/sub'));
        $id = newestTrash();
        assertNull(restoreFromTrash($base, $id, $error), 'three files do not fit in two');
        writeData('access', ['uploadLimits' => ['556' => 3]]);
        assertSame('users/556-U/sub', restoreFromTrash($base, $id, $error));
        sessionEnd();
        assertTrue(is_file($base . '/users/556-U/sub/c.png'));
        assertFalse(isset(readData('trash')[$id]));
    });

    test('a restore goes over the total of bytes neither', function () {
        $base = trashAccount(['a.png' => 1000]);
        assertTrue(moveToTrash($base . '/users/556-U/a.png', 'users/556-U/a.png'));
        $id = newestTrash();
        // the folder filled up meanwhile, up to the last byte
        $fill = fopen($base . '/users/556-U/big.png', 'w');
        ftruncate($fill, USER_TOTAL_MAX_BYTES - 500);
        fclose($fill);
        assertNull(restoreFromTrash($base, $id, $error));
        sessionEnd();
        assertTrue(strpos($error, 'wolnego') !== false, $error);
    });

    test('a restore goes back into the folder the account has now', function () {
        $base = trashAccount(['a.png' => 10]);
        assertTrue(moveToTrash($base . '/users/556-U/a.png', 'users/556-U/a.png'));
        $id = newestTrash();
        rename($base . '/users/556-U', $base . '/users/556-Nowy');
        assertSame('users/556-Nowy/a.png', restoreFromTrash($base, $id, $error));
        sessionEnd();
        assertFalse(is_dir($base . '/users/556-U'), 'the old name is not made again');
    });

    test('the trash of an account has a budget of its own', function () {
        $base = trashAccount(['a.png' => 2 * 1048576]);
        // what was deleted before, hidden from the list but still in the trash
        writeData('trash', ['20260101000000-aaaaaaaa' => ['name' => 'old.png', 'from' => 'users/556-U/old.png', 'folder' => false,
            'size' => USER_TRASH_MAX_BYTES - 1048576, 'deleted' => time(), 'by' => 'U', 'hidden' => time()]]);
        assertSame(USER_TRASH_MAX_BYTES - 1048576, userTrashBytes('556'), 'an older entry counts by its path');

        assertFalse(moveToTrash($base . '/users/556-U/a.png', 'users/556-U/a.png', $error));
        assertTrue(is_file($base . '/users/556-U/a.png'), 'the file stays where it was');
        assertTrue(strpos($error, 'pełny') !== false, $error);
        sessionEnd();

        // Gallery administration does not bypass the owner's trash budget.
        sessionFor('sanakan-trash-admin', ['id' => '111', 'name' => 'A', 'avatar' => 'a']);
        assertFalse(moveToTrash($base . '/users/556-U/a.png', 'users/556-U/a.png'));
        sessionEnd();
        assertSame(USER_TRASH_MAX_BYTES - 1048576, userTrashBytes('556'), 'the original stays outside the full trash');
    });

    test('a full trash first drops what is past its days', function () {
        $base = trashAccount(['a.png' => 2 * 1048576]);
        $old = '20250101000000-bbbbbbbb';
        writeData('trash', [$old => ['name' => 'old.png', 'from' => 'users/556-U/old.png', 'folder' => false,
            'size' => USER_TRASH_MAX_BYTES - 1048576, 'deleted' => time() - (TRASH_DAYS + 1) * 86400, 'by' => 'U']]);
        assertTrue(moveToTrash($base . '/users/556-U/a.png', 'users/556-U/a.png'));
        sessionEnd();
        assertFalse(isset(readData('trash')[$old]));
    });

    test('the lock of an account can be taken again by the same request', function () {
        $first = accountLock('557');
        $second = accountLock('557');
        assertTrue(is_callable($first) && is_callable($second));
        $second();
        $first();
        $other = fileLock(dataDir() . '/locks/account-557.lock', true);
        assertTrue($other !== null, 'let go after both');
        fclose($other);
    });

    test('treeUse counts a file alone and the biggest one', function () {
        $dir = tempDir();
        mkdir($dir . '/sub');
        file_put_contents($dir . '/a', str_repeat('x', 3));
        file_put_contents($dir . '/sub/b', str_repeat('x', 7));
        assertSame([2, 10, 7], treeUse($dir));
        assertSame([1, 3, 3], treeUse($dir . '/a'));
        assertSame('556', folderOwner('users/556-U/a.png'));
        assertSame('556', folderOwner('users/556'));
        assertNull(folderOwner('users'));
        assertNull(folderOwner('other/556-U'));
    });
