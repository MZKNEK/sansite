<?php
    // inc/storage.php: a write that cannot be done leaves the old file as it was
    // and says so; and text from outside that is not UTF-8.

    test('writeData refuses what is not UTF-8 and keeps the old file', function () {
        assertTrue(writeData('access', ['note' => 'stare']));
        $file = dataDir() . '/access.json';
        $before = file_get_contents($file);

        assertFalse(writeData('access', ['note' => "z\xC3\x28"]), 'the failure is told');
        assertSame($before, file_get_contents($file), 'the file is as it was');
        assertSame('stare', readData('access')['note']);
        assertSame([], glob(dataDir() . '/*.tmp') ?: [], 'no temporary file is left behind');
    });

    test('updateDataFile writes nothing when the change cannot be JSON', function () {
        assertTrue(updateDataFile('addresses.json', function ($data) { return ['a' => 1]; }));
        $file = dataDir() . '/addresses.json';
        $before = file_get_contents($file);

        assertFalse(updateDataFile('addresses.json', function ($data) { return $data + ['b' => "\xFF"]; }));
        assertSame($before, file_get_contents($file), 'not emptied');
        assertTrue(updateDataFile('addresses.json', function ($data) { return $data + ['b' => 2]; }));
        assertSame(['a' => 1, 'b' => 2], json_decode(file_get_contents($file), true));
    });

    test('replaceFile puts the whole content or nothing', function () {
        $dir = tempDir();
        assertTrue(replaceFile($dir . '/a.json', '{"x":1}'));
        assertSame('{"x":1}', file_get_contents($dir . '/a.json'));
        assertFalse(replaceFile($dir . '/a.json', false), 'a failed json_encode() is no content');
        assertTrue(replaceFile($dir . '/a.json', ''), 'an empty string is content');
        assertSame('', file_get_contents($dir . '/a.json'));
        assertFalse(replaceFile($dir . '/missing/a.json', 'x'), 'a folder that is not there');
        assertSame(['a.json'], array_values(array_diff(scandir($dir), ['.', '..'])));
    });

    test('botWriteFile keeps the old file when json_encode failed', function () {
        $file = tempDir() . '/state.json';
        botWriteFile($file, '{"ok":true}');
        botWriteFile($file, json_encode(['x' => "\xFF"]));
        assertSame('{"ok":true}', file_get_contents($file));
    });

    test('validText replaces only what is not UTF-8', function () {
        assertSame('Zażółć & <b>', validText('Zażółć & <b>'), 'valid text stays as it is');
        assertSame("a\u{FFFD}b", validText("a\xFFb"));
        assertTrue(encodeData(['ua' => validText("Mozilla \xC3\x28")]) !== null);
        assertSame("x\u{FFFD}", cutText("x\xFFyz", 2), 'cutText takes text from outside');
    });

    test('nameError refuses a name that is not UTF-8', function () {
        assertTrue(is_string(nameError("zdj\xEAcie.png", true)));
        assertNull(nameError('zdjęcie.png', true));
    });
