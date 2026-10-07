<?php
    // The seams and refactors introduced while adding the tests: the data folder,
    // the bot URLs and the quiet folder walkers. Each one is a regression guard
    // for a change that no other test would notice.

    // runs $fn with a real PHP warning (not one silenced with @) turned into a
    // failure, so a missing @ on a folder that may not exist is caught
    function assertNoWarnings($fn, $message = '')
    {
        $seen = [];
        set_error_handler(function ($no, $str) use (&$seen) {
            if (!(error_reporting() & $no))
                return false;   // silenced with @
            $seen[] = $str;
            return true;
        });
        try {
            $fn();
        } finally {
            restore_error_handler();
        }
        test_assert($seen === [], ($message !== '' ? $message . ': ' : '') . 'warnings: ' . implode(' | ', $seen));
    }

    test('dataDir and botFile follow SITE_DATA_DIR', function () {
        assertSame(rtrim((string)SITE_DATA_DIR, '/\\'), dataDir());
        assertSame(dataDir() . '/bot-x', botFile('x'), 'the bot state lives in the same folder');
    });

    test('an empty SITE_DATA_DIR does not move the data folder', function () {
        $null = DIRECTORY_SEPARATOR === '\\' ? 'NUL' : '/dev/null';
        $handle = proc_open([PHP_BINARY, __DIR__ . '/datadir-child.php'],
            [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['file', $null, 'w']], $pipes, __DIR__);
        if (!is_resource($handle))
            test_assert(false, 'nie można uruchomić procesu potomnego');

        $out = (string)stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        assertSame(0, proc_close($handle), 'the child exited cleanly');
        // no realpath: inc/data may not exist yet on a fresh checkout
        $expected = str_replace('\\', '/', dirname(__DIR__) . '/inc/data');
        assertSame($expected, str_replace('\\', '/', trim($out)));
    });

    test('the bot URLs sit on botApiBase()', function () {
        assertSame('https://api.sanakan.pl', botApiBase());
        assertSame('https://api.sanakan.pl/api/health', botHealthUrl());
        assertSame('https://api.sanakan.pl/api/Info/commands', botCommandsUrl());
        assertSame('https://api.sanakan.pl/api/Info/commands/private', botPrivateUrl());
        assertSame('https://api.sanakan.pl/api/alive', botApiAliveUrl());
        assertSame('https://api.sanakan.pl/api/User/discord/123/permissions', botRolesUrl('123'));
    });

    test('the folder walkers stay quiet about a missing folder', function () {
        $missing = tempDir() . '/nope';
        assertNoWarnings(function () use ($missing) {
            $files = [];
            $bytes = 0;
            treeSize($missing);
            treeUse($missing);
            folderUse($missing);
            folderTotals($missing);
            listNames($missing, '');
            zipCollect($missing, 'i', [], $files, $bytes);
            removeTree($missing);
        });
    });
