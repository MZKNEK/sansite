<?php
    // inc/system.php: the server's resources, read from /proc. On a system
    // without /proc (the tests may run on Windows) the functions return null,
    // which the panel already handles, so only the Linux case is checked.

    if (!is_dir('/proc')) {
        test('system stats need /proc (skipped here)', function () {
            assertNull(systemCpuTimes());
            assertNull(systemMemory());
        });
        return;
    }

    test('systemCpuTimes and systemMemory', function () {
        $cpu = systemCpuTimes();
        assertTrue(isset($cpu['idle'], $cpu['total']));
        assertTrue($cpu['total'] > 0);

        $memory = systemMemory();
        assertTrue(isset($memory['total'], $memory['available']));
        assertTrue($memory['total'] > 0);
    });

    test('systemCpuInfo, systemUptime and systemName', function () {
        assertTrue(systemCpuInfo()['cores'] >= 1);
        assertTrue(systemUptime() >= 0);
        assertContains('jądro', systemName());
    });

    test('systemProcesses groups the workers by memory', function () {
        $processes = systemProcesses();
        assertTrue(is_array($processes));
        foreach ($processes as $program) {
            assertTrue(isset($program['count'], $program['bytes']));
            assertTrue($program['bytes'] > 0);
        }
    });
