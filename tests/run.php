<?php
    // Runs the test suite: php tests/run.php. Prints a dot per passing case and
    // an F per failing one, then the failures; exits non-zero when any failed.
    require __DIR__ . '/bootstrap.php';

    foreach (glob(__DIR__ . '/test_*.php') ?: [] as $file) {
        $GLOBALS['__suite'] = basename($file, '.php');
        require $file;
    }

    $passed = 0;
    $failed = 0;
    $failures = [];
    $progress = '';
    foreach ($GLOBALS['__tests'] as [$suite, $name, $fn]) {
        clearData();
        try {
            $fn();
            $passed++;
            $progress .= '.';
        } catch (Throwable $e) {
            $failed++;
            $progress .= 'F';
            $failures[] = $suite . ' :: ' . $name . "\n      " . $e->getMessage();
        }
    }

    // printed only now, so a session a test starts never follows output
    // ("headers already sent")
    fwrite(STDOUT, $progress . "\n\n" . $passed . ' passed, ' . $failed . ' failed, ' . $GLOBALS['__assertions'] . " assertions\n");
    if ($failed) {
        fwrite(STDOUT, "\nFailures:\n");
        foreach ($failures as $failure)
            fwrite(STDOUT, '  ' . $failure . "\n");
        exit(1);
    }
    fwrite(STDOUT, "OK\n");
