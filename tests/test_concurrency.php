<?php
    // inc/auth.php: two processes changing the same file at once must not lose a
    // change (updateDataFile() locks it with flock).

    test('updateDataFile loses no change with several processes', function () {
        $processes = 4;
        $each = 150;
        $file = 'concurrency-counter.json';
        $path = dataDir() . '/' . $file;

        $php = PHP_BINARY;
        $child = __DIR__ . '/concurrency-child.php';
        $env = array_merge(getenv() ?: [], ['SANAKAN_TEST_DATA' => SITE_DATA_DIR]);
        $null = DIRECTORY_SEPARATOR === '\\' ? 'NUL' : '/dev/null';

        $running = [];
        for ($i = 0; $i < $processes; $i++) {
            $handle = proc_open(
                [$php, $child, $file, (string)$each],
                [0 => ['pipe', 'r'], 1 => ['file', $null, 'w'], 2 => ['file', $null, 'w']],
                $pipes, __DIR__, $env
            );
            if (!is_resource($handle))
                test_assert(false, 'nie można uruchomić procesu potomnego');
            $running[] = $handle;
        }

        foreach ($running as $handle)
            assertSame(0, proc_close($handle), 'a child exited cleanly');

        $data = json_decode((string)@file_get_contents($path), true);
        assertTrue(is_array($data));
        assertSame($processes * $each, $data['n'], 'every increment is in the file');
    });
