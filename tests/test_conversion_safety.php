<?php
    function conversionFixture()
    {
        $base = tempDir();
        file_put_contents($base . '/a.png', 'original');
        $snapshot = conversionSnapshot($base . '/a.png');
        $candidate = dirname($snapshot['input']) . '/candidate-' . bin2hex(random_bytes(8)) . '.webm';
        file_put_contents($candidate, 'small');
        return [$base, $snapshot, $candidate];
    }

    test('conversion chooses a free target at commit and preserves an independent target', function () {
        [$base, $snapshot, $candidate] = conversionFixture();
        file_put_contents($base . '/a.webm', 'independent');
        $rel = commitConversion($base . '/a.png', 'a.png', $candidate, $base . '/a.webm', $snapshot['fingerprint'], $error);
        assertTrue(is_string($rel), $error ?? '');
        assertFalse($rel === 'a.webm');
        assertSame('independent', file_get_contents($base . '/a.webm'));
        assertSame('small', file_get_contents($base . '/' . $rel));
        assertSame('original', file_get_contents(trashDir() . '/' . array_key_first(trashItems()) . '/a.png'));
        assertSame([], glob(operationsDir() . '/*.json') ?: []);
    });

    test('source hash detects a same-size same-mtime replacement and snapshot remains stable', function () {
        [$base, $snapshot, $candidate] = conversionFixture();
        file_put_contents($base . '/a.png', 'modified');
        touch($base . '/a.png', $snapshot['fingerprint']['mtime']);
        assertFalse(commitConversion($base . '/a.png', 'a.png', $candidate, $base . '/a.webm', $snapshot['fingerprint'], $error));
        assertTrue(str_contains($error, 'Źródło'));
        assertSame('original', file_get_contents($snapshot['input']));
        assertSame('modified', file_get_contents($base . '/a.png'));
        assertSame([], trashItems());
    });

    test('conversion recovery handles original moved but result not published, idempotently', function () {
        [$base, $snapshot, $candidate] = conversionFixture();
        $id = '20250101000000-abcdef01';
        mkdir(trashDir() . '/' . $id, 0750, true);
        $op = ['kind' => 'conversion', 'trash' => $id, 'src' => $base . '/a.png', 'dst' => trashDir() . '/' . $id . '/a.png',
            'target' => $base . '/a.webm', 'candidate' => $candidate, 'sourceHash' => hash('sha256', 'original'),
            'candidateHash' => hash('sha256', 'small'), 'item' => ['name' => 'a.png', 'from' => 'a.png', 'size' => 8, 'deleted' => time()]];
        rename($op['src'], $op['dst']);
        operationNote($op);
        assertSame(1, maintenanceRun(time())['recovered']);
        assertSame('original', file_get_contents($op['src']));
        assertSame(0, maintenanceRun(time())['recovered']);
        assertFalse(is_file($candidate));
    });

    test('active operation cannot be recovered even when old', function () {
        $base = tempDir();
        file_put_contents($base . '/a.png', 'x');
        $id = '20250101000000-abcdef02';
        mkdir(trashDir() . '/' . $id, 0750, true);
        $note = beginOperation(['kind' => 'trash', 'trash' => $id, 'src' => $base . '/a.png', 'dst' => trashDir() . '/' . $id . '/a.png',
            'item' => ['name' => 'a.png', 'from' => 'a.png', 'size' => 1, 'deleted' => time()]]);
        assertSame([0, []], recoverOperations(time() + OPERATION_STALE + 1));
        assertTrue(is_file(operationsDir() . '/' . $note . '.json'));
        endOperation($note);
    });

    test('maintenance reports corrupt trash and report write failures without recording success', function () {
        file_put_contents(dataFile('trash'), 'broken');
        $report = maintenanceRun(time());
        assertTrue(count($report['problems']) > 0);
        assertFalse(isset(maintenanceState()['succeeded']));
        file_put_contents(dataFile('trash'), '{}');
        @unlink(dataFile('maintenance'));
        mkdir(dataFile('maintenance'));
        $report = maintenanceRun(time());
        assertTrue(count($report['problems']) > 0);
    });

    test('failed trash index and failed rollback preserve the journal for recovery', function () {
        $base = tempDir();
        file_put_contents($base . '/a.png', 'original');
        $GLOBALS['SANAKAN_GALLERY_IO'] = function ($kind, $src, $dst) use ($base) {
            if ($src === $base . '/a.png') {
                $ok = rename($src, $dst);
                file_put_contents(dataFile('trash'), 'broken');
                return $ok;
            }
            return false; // rollback fails deterministically, also on Windows
        };
        try {
            assertFalse(moveToTrash($base . '/a.png', 'a.png', $error));
            assertTrue(str_contains($error, 'dziennik'));
        } finally { unset($GLOBALS['SANAKAN_GALLERY_IO']); }
        assertSame(1, count(glob(operationsDir() . '/*.json')));
        file_put_contents(dataFile('trash'), '{}');
        assertSame(1, maintenanceRun(time() + OPERATION_STALE + 1)['recovered']);
        assertSame(1, count(trashItems()));
        assertSame(0, maintenanceRun(time() + OPERATION_STALE + 1)['recovered']);
    });

    test('conversion failure after original move keeps a recoverable operation', function () {
        [$base, $snapshot, $candidate] = conversionFixture();
        $GLOBALS['SANAKAN_GALLERY_IO'] = function ($kind, $src, $dst) use ($candidate) {
            return $src === $candidate ? false : null;
        };
        try { assertFalse(commitConversion($base . '/a.png', 'a.png', $candidate, $base . '/a.webm', $snapshot['fingerprint'], $error)); }
        finally { unset($GLOBALS['SANAKAN_GALLERY_IO']); }
        assertTrue(str_contains($error, 'dziennik'));
        assertSame(1, count(glob(operationsDir() . '/*.json')));
        assertSame(1, maintenanceRun(time() + OPERATION_STALE + 1)['recovered']);
        assertSame('original', file_get_contents($base . '/a.png'));
    });

    test('recovery refuses traversal and symlink-free files outside gallery', function () {
        $base = tempDir();
        $id = '20250101000000-abcdef03';
        $note = operationNote(['kind' => 'trash', 'trash' => $id, 'src' => $base . '/../outside.png',
            'dst' => trashDir() . '/' . $id . '/a.png', 'item' => ['name' => 'a.png', 'from' => 'a.png', 'size' => 1, 'deleted' => time()]]);
        $report = maintenanceRun(time());
        assertSame(0, $report['recovered']);
        assertTrue(count($report['problems']) > 0);
        assertTrue(is_file($note));
    });

    test('manual preview refuses acceptance after the source changes', function () {
        if (!hasGd() || !function_exists('imagewebp')) return;
        $base = tempDir();
        $image = imagecreatetruecolor(64, 64);
        for ($y = 0; $y < 64; $y++)
            for ($x = 0; $x < 64; $x++) imagesetpixel($image, $x, $y, mt_rand(0, 0xffffff));
        imagepng($image, $base . '/a.png');
        $user = ['id' => '111', 'name' => 'Gallery'];
        $preview = startWebpPreview($base . '/a.png', 'a.png', 40, $user);
        assertTrue(is_array($preview), is_string($preview) ? $preview : '');
        $original = file_get_contents($base . '/a.png');
        $mtime = filemtime($base . '/a.png');
        $changed = $original;
        $changed[strlen($changed) - 1] = chr(ord($changed[strlen($changed) - 1]) ^ 1);
        file_put_contents($base . '/a.png', $changed);
        touch($base . '/a.png', $mtime);
        $result = acceptWebpPreview($base, $preview['token'], $user);
        assertTrue(is_string($result) && str_contains($result, 'Źródło'));
        assertSame($changed, file_get_contents($base . '/a.png'));
        assertSame([], trashItems());
    });

    test('same-name WebP replacement journals and preserves the actual original', function () {
        if (!hasGd() || !function_exists('imagewebp')) return;
        $base = tempDir();
        $image = imagecreatetruecolor(64, 64);
        for ($y = 0; $y < 64; $y++)
            for ($x = 0; $x < 64; $x++) imagesetpixel($image, $x, $y, mt_rand(0, 0xffffff));
        imagewebp($image, $base . '/a.webp', 100);
        @mkdir(webpPreviewDir(), 0750, true);
        $candidate = webpPreviewDir() . '/test.webp';
        imagewebp($image, $candidate, 40);
        $original = file_get_contents($base . '/a.webp');
        $result = file_get_contents($candidate);
        assertSame('a.webp', commitConversion($base . '/a.webp', 'a.webp', $candidate, $base . '/a.webp', fileFingerprint($base . '/a.webp'), $error));
        assertSame($result, file_get_contents($base . '/a.webp'));
        assertSame($original, file_get_contents(trashDir() . '/' . array_key_first(trashItems()) . '/a.webp'));
    });

    test('an invalid journal prevents deleting expired trash', function () {
        $id = '20250101000000-abcdef09';
        trashEntry($id, (TRASH_DAYS + 1) * 86400);
        @mkdir(operationsDir(), 0750, true);
        file_put_contents(operationsDir() . '/broken.json', '{broken');
        $report = maintenanceRun(time());
        assertSame(0, $report['removed']);
        assertTrue(count($report['problems']) > 0);
        assertTrue(is_file(trashDir() . '/' . $id . '/a.png'));
    });
