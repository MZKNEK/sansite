<?php
    function quotaThrows($class, $callback)
    {
        try { $callback(); }
        catch (Throwable $error) { assertTrue($error instanceof $class, get_class($error) . ': ' . $error->getMessage()); return; }
        test_assert(false, 'Expected ' . $class);
    }

    test('four quotas inherit, explicitly override and reset without reusing revisions', function () {
        $id = '123456789012345678';
        assertSame(['files' => 200, 'fileBytes' => 10485760, 'totalBytes' => 104857600, 'trashBytes' => 52428800], accountLimits($id)['values']);
        assertTrue(saveGalleryLimits(null, ['files' => '200', 'fileBytes' => '10', 'totalBytes' => '100', 'trashBytes' => '50'], '0', '999'));
        assertTrue(saveGalleryLimits($id, ['files' => '200', 'trashBytes' => '70'], '0', '999'));
        saveGalleryLimits(null, ['files' => '300', 'fileBytes' => '20', 'totalBytes' => '100', 'trashBytes' => '50'], 1, '999');
        assertSame(200, accountLimits($id)['values']['files'], 'equal value remains explicit');
        assertSame(20 * 1048576, accountLimits($id)['values']['fileBytes']);
        saveGalleryLimits($id, [], 1, '999', ['files', 'trashBytes']);
        assertSame(300, accountLimits($id)['values']['files']);
        assertSame(2, accountLimits($id)['revision']);
        quotaThrows(UnexpectedValueException::class, function () use ($id) { saveGalleryLimits($id, ['files' => 1], 1, '999'); });
        assertSame([], accountLimits($id)['overrides']);
        assertTrue(str_contains(file_get_contents(historyFile()), $id));
    });

    test('quota input rejects malformed numbers, missing revisions, unknown fields and invalid IDs', function () {
        foreach ([0, -1, '12abc', '1e2', '1.5', ['2'], '', '10001', '99999999999999999999999'] as $value)
            quotaThrows(InvalidArgumentException::class, function () use ($value) { saveGalleryLimits('123456789012345678', ['files' => $value], 0, '999'); });
        foreach ([null, [], '-1', '1abc'] as $revision)
            quotaThrows(InvalidArgumentException::class, function () use ($revision) { saveGalleryLimits('123456789012345678', ['files' => 1], $revision, '999'); });
        quotaThrows(InvalidArgumentException::class, function () { saveGalleryLimits('123', ['files' => 1], 0, '999'); });
        quotaThrows(InvalidArgumentException::class, function () { saveGalleryLimits('123456789012345678', ['other' => 1], 0, '999'); });
        quotaThrows(InvalidArgumentException::class, function () { saveGalleryLimits('123456789012345678', ['totalBytes' => 1001], 0, '999'); });
        quotaThrows(InvalidArgumentException::class, function () { saveGalleryLimits('123456789012345678', ['fileBytes' => 129], 0, '999'); });
        saveGalleryLimits('123456789012345678', ['files' => 10000, 'fileBytes' => 128, 'totalBytes' => 1000, 'trashBytes' => 1000], 0, '999');
        assertSame(10000, accountLimits('123456789012345678')['values']['files']);
    });

    test('fresh policy enforces exact bytes and permits shrinking an over-limit account', function () {
        $id = '123456789012345678';
        $base = tempDir();
        mkdir($base . '/users/' . $id . '-U', 0750, true);
        file_put_contents($base . '/users/' . $id . '-U/a.png', str_repeat('a', 1048576));
        saveGalleryLimits($id, ['files' => 1, 'fileBytes' => 1, 'totalBytes' => 1], 0, '999');
        assertNull(accountQuotaError($base, $id, 0, 0, 1048576));
        assertTrue(is_string(accountQuotaError($base, $id, 0, 0, 1048577)));
        assertTrue(is_string(accountQuotaError($base, $id, 1, 1, 1)));
        assertNull(accountQuotaError($base, $id, 0, -1, 1048575));
        assertSame(1, accountUsage($base, $id)['values']['files']);
        sessionFor('quota-panel', ['id' => '999', 'name' => 'Panel', 'avatar' => 'a']);
        assertNull(accountQuotaError($base, $id, 1, 1048577, 1048577, true));
        assertTrue(is_string(accountQuotaError($base, $id, 1, 1048577, 1048577)));
        sessionEnd();
        sessionFor('quota-gallery-admin', ['id' => '111', 'name' => 'Gallery', 'avatar' => 'a']);
        assertTrue(is_string(accountQuotaError($base, $id, 1, 1048577, 1048577, true)));
        sessionEnd();
    });

    test('corrupted access and invalid quota schemas fail closed rather than raising limits', function () {
        writeData('roles', ['556' => ['roles' => ['user' => true], 'checked' => time()]]);
        file_put_contents(dataFile('access'), '{broken');
        quotaThrows(DataStoreException::class, function () { isGalleryUploaderId('556'); });
        file_put_contents(dataFile('access'), '{"galleryUploaders":null}');
        quotaThrows(DataStoreException::class, function () { isGalleryUploaderId('556'); });
        file_put_contents(dataFile('access'), '{}');
        file_put_contents(dataFile('settings'), '{"galleryLimitDefaults":null}');
        quotaThrows(DataStoreException::class, function () { accountLimits('556'); });
        file_put_contents(dataDir() . '/initialized', 'yes');
        @unlink(dataFile('access'));
        quotaThrows(DataStoreException::class, function () { readData('access'); });
    });

    test('hidden trash counts, account limits without a folder remain editable', function () {
        $id = '123456789012345678';
        writeData('trash', ['entry' => ['name' => 'x.png', 'from' => 'users/' . $id . '-Old/x.png', 'deleted' => time(), 'size' => 12, 'hidden' => time()]]);
        saveGalleryLimits($id, ['trashBytes' => 1], 0, '999');
        $usage = accountUsage(tempDir(), $id);
        assertSame(12, $usage['values']['trashBytes']);
        assertSame(12, $usage['hiddenBytes']);
        assertSame(0, $usage['values']['files']);
    });

    function quotaChild($mode, $base, $signal)
    {
        $null = DIRECTORY_SEPARATOR === '\\' ? 'NUL' : '/dev/null';
        return proc_open([PHP_BINARY, __DIR__ . '/quota-child.php', $mode, $base, $signal],
            [0 => ['file', $null, 'r'], 1 => ['file', $null, 'w'], 2 => ['file', $null, 'w']], $pipes, __DIR__,
            array_merge(getenv() ?: [], ['SANAKAN_TEST_DATA' => SITE_DATA_DIR]));
    }

    function quotaWait($condition)
    {
        $deadline = microtime(true) + 10;
        while (!$condition()) {
            clearstatcache();
            if (microtime(true) > $deadline) test_assert(false, 'child signal timeout');
            usleep(10000);
        }
    }

    test('two processes cannot both take the last file slot', function () {
        $base = tempDir();
        mkdir($base . '/users/123456789012345678-U', 0750, true);
        saveGalleryLimits('123456789012345678', ['files' => 1], 0, '999');
        $signal = tempDir() . '/signal';
        $first = quotaChild('add', $base, $signal);
        $second = quotaChild('add', $base, $signal);
        quotaWait(function () use ($signal) { return count(glob($signal . '.ready-*')) === 2; });
        file_put_contents($signal . '.go', '1');
        assertSame(0, proc_close($first));
        assertSame(0, proc_close($second));
        assertSame(1, accountUsage($base, '123456789012345678')['values']['files']);
    });

    test('admin policy write waits for an already committing operation', function () {
        $base = tempDir();
        $signal = tempDir() . '/signal';
        $reader = quotaChild('policy-reader', $base, $signal);
        quotaWait(function () use ($signal) { return is_file($signal . '.ready'); });
        $writer = quotaChild('policy-writer', $base, $signal);
        quotaWait(function () use ($signal) { return is_file($signal . '.started'); });
        file_put_contents($signal . '.go', '1');
        assertSame(0, proc_close($reader));
        assertSame(0, proc_close($writer));
        assertSame('200', file_get_contents($signal . '.result'));
        assertSame(1, accountLimits('123456789012345678')['values']['files']);
    });
