<?php
    function galleryCli($args)
    {
        $process = proc_open(array_merge([PHP_BINARY, __DIR__ . '/gallery-cli.php'], $args),
            [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, __DIR__,
            array_merge(getenv() ?: [], ['SANAKAN_TEST_DATA' => SITE_DATA_DIR]));
        fclose($pipes[0]);
        $output = stream_get_contents($pipes[1]); fclose($pipes[1]);
        $error = stream_get_contents($pipes[2]); fclose($pipes[2]);
        return [proc_close($process), $output, $error];
    }

    test('migration dry-run preserves old values, then migrates once and initializes required documents', function () {
        $id = '123456789012345678';
        writeData('access', ['uploadLimits' => [$id => 75], 'note' => 'keep']);
        $before = file_get_contents(dataFile('access'));
        assertSame(0, galleryCli(['migrate', '--dry-run'])[0]);
        assertSame($before, file_get_contents(dataFile('access')));
        assertFalse(is_file(dataDir() . '/initialized'));
        assertSame(0, galleryCli(['migrate'])[0]);
        assertSame(75, accountLimits($id)['values']['files']);
        assertSame(200, accountLimits('234567890123456789')['values']['files']);
        assertSame('keep', readData('access')['note']);
        assertFalse(isset(readData('access')['uploadLimits']));
        foreach (['access', 'settings', 'trash', 'shares', 'roles', 'sessions'] as $name)
            assertTrue(is_file(dataFile($name)));
        $after = file_get_contents(dataFile('access'));
        assertSame(0, galleryCli(['migrate'])[0]);
        assertSame($after, file_get_contents(dataFile('access')));
    });

    test('migration reports a conflict and preserves both formats for a decision', function () {
        $id = '123456789012345678';
        writeData('access', ['uploadLimits' => [$id => 75], 'galleryLimitOverrides' => [$id => ['revision' => 1, 'values' => ['files' => 80]]]]);
        $before = file_get_contents(dataFile('access'));
        [$code, $output, $error] = galleryCli(['migrate']);
        assertSame(1, $code);
        assertTrue(str_contains($error, 'Konflikt'));
        assertSame($before, file_get_contents(dataFile('access')));
    });

    test('empty installation initializes real documents and later missing files fail closed', function () {
        [$code, $output, $error] = galleryCli(['migrate']);
        assertSame(0, $code, $error);
        assertSame([], readData('access'));
        unlink(dataFile('access'));
        quotaThrows(DataStoreException::class, function () { accountLimits('123456789012345678'); });
    });

    test('maintenance CLI emits corrupt-index errors on stderr and exits nonzero', function () {
        file_put_contents(dataFile('trash'), '{broken');
        [$code, $output, $error] = galleryCli(['maintenance']);
        assertSame(1, $code);
        assertTrue(str_contains($error, 'niedostępne'));
        assertFalse(isset(maintenanceState()['succeeded']));
    });
