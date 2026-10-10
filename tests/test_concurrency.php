<?php
    // inc/storage.php: processes changing the same file at once must not lose a
    // change, and one reading it meanwhile must never find it empty or cut.

    test('updateData loses no change and no reader sees half a file', function () {
        $processes = 4;
        $each = 150;
        $name = 'concurrency-counter';
        assertTrue(writeData($name, ['n' => 0]));

        $php = PHP_BINARY;
        $child = __DIR__ . '/concurrency-child.php';
        $env = array_merge(getenv() ?: [], ['SANAKAN_TEST_DATA' => SITE_DATA_DIR]);
        $null = DIRECTORY_SEPARATOR === '\\' ? 'NUL' : '/dev/null';

        $start = function ($args) use ($php, $child, $env, $null) {
            $handle = proc_open(
                array_merge([$php, $child], $args),
                [0 => ['pipe', 'r'], 1 => ['file', $null, 'w'], 2 => ['file', $null, 'w']],
                $pipes, __DIR__, $env
            );
            if (!is_resource($handle))
                test_assert(false, 'nie można uruchomić procesu potomnego');

            return $handle;
        };

        $running = [];
        for ($i = 0; $i < $processes; $i++)
            $running[] = $start(['add', $name, (string)$each]);
        $reader = $start(['read', $name, '400']);

        foreach ($running as $handle)
            assertSame(0, proc_close($handle), 'a child exited cleanly');
        assertSame(0, proc_close($reader), 'the reader always found the whole file');

        forgetData($name);
        assertSame($processes * $each, readData($name)['n'], 'every increment is in the file');
        assertSame([], glob(dataDir() . '/*.tmp') ?: [], 'no temporary file is left behind');
    });

    test('"name" and "name.json" are one file with one lock', function () {
        assertSame(dataFile('access'), dataFile('access.json'));
        assertSame(dataLockFile('access'), dataLockFile('access.json'));
        assertTrue(updateDataFile('access.json', function ($data) { return ['a' => 1]; }));
        assertSame(['a' => 1], readData('access'));
    });

    test('updateData starts from the disk, not from the read of this request', function () {
        assertTrue(writeData('settings', ['a' => 1]));
        assertSame(['a' => 1], readData('settings'));
        // another process writes meanwhile
        file_put_contents(dataFile('settings'), json_encode(['a' => 1, 'b' => 2]));
        assertTrue(updateData('settings', function (&$data) { $data['c'] = 3; }));
        assertSame(['a' => 1, 'b' => 2, 'c' => 3], readData('settings'));
    });

    test('updateData gives back what the change returns and writes only a change', function () {
        assertTrue(writeData('requests', ['x' => 1]));
        $mtime = filemtime(dataFile('requests'));
        $inode = fileinode(dataFile('requests'));
        assertTrue(updateData('requests', function (&$data) { return $data['x']; }, $result));
        assertSame(1, $result);
        clearstatcache();
        assertSame($inode, fileinode(dataFile('requests')), 'nothing changed, nothing written');
        assertSame($mtime, filemtime(dataFile('requests')));
    });

    test('a broken file is never written over by a change', function () {
        file_put_contents(dataFile('access'), '{"galleryViewers": {"1');
        forgetData('access');
        try { readData('access'); test_assert(false, 'broken permissions must fail closed'); }
        catch (DataStoreException $error) { assertTrue(true); }
        assertFalse(updateData('access', function (&$data) { $data['x'] = 1; }));
        assertSame('{"galleryViewers": {"1', file_get_contents(dataFile('access')), 'the broken file is kept as it is');

        file_put_contents(dataFile('hud'), '');
        assertFalse(updateData('hud', function (&$data) { $data['x'] = 1; }), 'an empty file is broken too');
    });

    test('writeData over a broken file sets it aside first', function () {
        file_put_contents(dataFile('panel-stats'), 'not json');
        assertTrue(writeData('panel-stats', ['time' => 1]));
        assertSame(['time' => 1], readData('panel-stats'));
        $aside = glob(dataFile('panel-stats') . '.broken-*') ?: [];
        assertSame(1, count($aside));
        assertSame('not json', file_get_contents($aside[0]));
    });

    test('a broken summary of diag is made again', function () {
        @mkdir(diagDir(), 0750, true);
        file_put_contents(diagDir() . '/pages.json', '{"cut');
        assertTrue(diagUpdate('pages.json', function ($data) { return $data + ['x' => 1]; }));
        assertSame(['x' => 1], json_decode(file_get_contents(diagDir() . '/pages.json'), true));
    });

    test('a media job is not taken when taking it cannot be written', function () {
        addMediaJob('a.png', '1', 'A', 10, 'webp');
        file_put_contents(dataFile('media-jobs'), '{"broken');
        assertNull(claimMediaJob());
        assertNull(addMediaJob('b.png', '1', 'A', 10, 'webp'), 'nor queued');
    });
