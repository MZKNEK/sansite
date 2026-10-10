<?php
    // inc/maintenance.php: the cleanup run by its own cron job; the trash past
    // its days goes without anyone opening the site, and operations cut short
    // are finished, undone or reported.

    // a trash entry with a file, deleted $age seconds ago
    function trashEntry($id, $age, $files = ['a.png' => 'x'])
    {
        $folder = count($files) > 1;
        $dir = trashDir() . '/' . $id;
        $name = $folder ? 'folder' : (string)array_key_first($files);
        mkdir($folder ? $dir . '/folder' : $dir, 0750, true);
        foreach ($files as $file => $content)
            file_put_contents($dir . '/' . ($folder ? 'folder/' : '') . $file, $content);
        updateData('trash', function (&$items) use ($id, $name, $folder, $age, $files) {
            $items[$id] = ['name' => $name, 'from' => $name, 'folder' => $folder, 'size' => strlen(implode('', $files)),
                'deleted' => time() - $age, 'by' => 'test'];
        });
    }

    // a note of an operation, as one cut short $age seconds ago left it
    function operationNote($operation, $age = 3600)
    {
        @mkdir(operationsDir(), 0750, true);
        $file = operationsDir() . '/' . date('YmdHis') . '-' . bin2hex(random_bytes(4)) . '.json';
        file_put_contents($file, json_encode(['format' => 1, 'started' => time() - $age] + $operation));

        return $file;
    }

    test('the trash past its days goes without anyone opening the site', function () {
        $days = TRASH_DAYS * 86400;
        trashEntry('20250101000000-00000001', $days + 60);
        trashEntry('20250101000000-00000002', $days - 60);
        $report = maintenanceRun(time());
        assertSame(1, $report['removed']);
        assertSame(0, $report['backlog']);
        assertSame([], $report['problems']);
        assertFalse(isset(readData('trash')['20250101000000-00000001']));
        assertFalse(file_exists(trashDir() . '/20250101000000-00000001'), 'its files too');
        assertTrue(isset(readData('trash')['20250101000000-00000002']), 'one not past its days stays');
        assertTrue(maintenanceState()['succeeded'] >= time() - 5, 'the success is noted');
        assertFalse(maintenanceLate());
    });

    test('the boundary is TRASH_DAYS to the second', function () {
        $now = time();
        trashEntry('20250101000000-00000003', TRASH_DAYS * 86400);
        maintenanceRun($now);
        assertTrue(isset(readData('trash')['20250101000000-00000003']), 'exactly the days: still there');
        maintenanceRun($now + 1);
        assertFalse(isset(readData('trash')['20250101000000-00000003']));
    });

    test('--dry-run says what it would do and changes nothing', function () {
        trashEntry('20250101000000-00000004', TRASH_DAYS * 86400 + 60);
        $report = maintenanceRun(time(), true);
        assertSame(1, $report['removed']);
        assertTrue(isset(readData('trash')['20250101000000-00000004']));
        assertSame([], maintenanceState(), 'nothing is noted either');
    });

    test('two runs at once: the second leaves it to the first', function () {
        @mkdir(dataDir() . '/locks', 0750, true);
        $held = fopen(dataDir() . '/locks/maintenance-run.lock', 'c');
        flock($held, LOCK_EX);
        assertSame(['busy' => true], maintenanceRun(time()));
        fclose($held);
    });

    test('a big folder goes in parts and cannot be restored once begun', function () {
        $id = '20250101000000-00000005';
        trashEntry($id, TRASH_DAYS * 86400 + 60, ['a.png' => 'x', 'b.png' => 'y', 'c.png' => 'z']);
        // the time is up at once: nothing more than the mark is done
        assertSame('partial', purgeTrashEntry($id, time() - TRASH_DAYS * 86400, microtime(true)));
        assertTrue(!empty(readData('trash')[$id]['purging']));
        assertNull(restoreFromTrash(tempDir(), $id, $error));
        assertTrue(strpos($error, 'usuwane') !== false, $error);

        $report = maintenanceRun(time());
        assertSame(1, $report['removed'], 'the next run finishes it');
        assertFalse(file_exists(trashDir() . '/' . $id));
    });

    test('a move to the trash cut short after the move gets its entry', function () {
        $id = '20250101000000-00000006';
        mkdir(trashDir() . '/' . $id, 0750, true);
        file_put_contents(trashDir() . '/' . $id . '/a.png', 'x');
        $item = ['name' => 'a.png', 'from' => 'a.png', 'folder' => false, 'size' => 1, 'deleted' => time(), 'by' => 'test'];
        $note = operationNote(['kind' => 'trash', 'trash' => $id, 'src' => tempDir() . '/a.png', 'dst' => trashDir() . '/' . $id . '/a.png', 'item' => $item]);
        $report = maintenanceRun(time());
        assertSame(1, $report['recovered']);
        assertSame($item, readData('trash')[$id]);
        assertFalse(is_file($note), 'the note goes');
    });

    test('a move to the trash cut short before the move is undone', function () {
        $id = '20250101000000-00000007';
        $base = tempDir();
        file_put_contents($base . '/a.png', 'x');
        mkdir(trashDir() . '/' . $id, 0750, true);
        operationNote(['kind' => 'trash', 'trash' => $id, 'src' => $base . '/a.png', 'dst' => trashDir() . '/' . $id . '/a.png', 'item' => []]);
        assertSame(1, maintenanceRun(time())['recovered']);
        assertFalse(is_dir(trashDir() . '/' . $id), 'the empty folder it was to go into goes');
        assertTrue(is_file($base . '/a.png'), 'the file stays');
        assertFalse(isset(readData('trash')[$id]));
    });

    test('a restore cut short after the move drops its entry', function () {
        $id = '20250101000000-00000008';
        trashEntry($id, 60);
        $base = tempDir();
        rename(trashDir() . '/' . $id . '/a.png', $base . '/a.png');
        operationNote(['kind' => 'restore', 'trash' => $id, 'src' => trashDir() . '/' . $id . '/a.png', 'dst' => $base . '/a.png']);
        assertSame(1, maintenanceRun(time())['recovered']);
        assertFalse(isset(readData('trash')[$id]));
        assertFalse(is_dir(trashDir() . '/' . $id));
    });

    test('an unclear operation is only reported, and a young one left alone', function () {
        $base = tempDir();
        file_put_contents($base . '/a.png', 'x');
        file_put_contents($base . '/b.png', 'x');
        $unclear = operationNote(['kind' => 'trash', 'trash' => 'x', 'src' => $base . '/a.png', 'dst' => $base . '/b.png', 'item' => []]);
        $young = operationNote(['kind' => 'trash', 'trash' => 'y', 'src' => $base . '/a.png', 'dst' => $base . '/c.png', 'item' => []], 10);

        $report = maintenanceRun(time());
        assertSame(0, $report['recovered']);
        assertSame(1, count($report['problems']));
        assertTrue(is_file($unclear) && is_file($young), 'both notes stay');
        assertTrue(is_file($base . '/a.png') && is_file($base . '/b.png'), 'no file is touched');
        assertFalse(isset(maintenanceState()['succeeded']), 'a run with problems is no success');
        assertTrue(maintenanceLate());
    });

    test('a folder in the trash without an entry is reported, not deleted', function () {
        mkdir(trashDir() . '/20250101000000-00000009', 0750, true);
        $report = maintenanceRun(time());
        assertSame(1, count($report['problems']));
        assertTrue(is_dir(trashDir() . '/20250101000000-00000009'));
    });

    test('moving to the trash and back leaves no note behind', function () {
        $base = tempDir();
        file_put_contents($base . '/a.png', 'x');
        assertTrue(moveToTrash($base . '/a.png', 'a.png'));
        $id = (string)array_key_first(trashItems());
        assertSame('a.png', restoreFromTrash($base, $id, $error));
        assertSame([], glob(operationsDir() . '/*.json') ?: []);
    });
