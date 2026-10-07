<?php
    // inc/gallery.php: the shared links, the background conversion queue, the
    // ZIP walk, the folder helpers and the search.

    test('shares are created and expire', function () {
        $token = createShare('folder/one', 7, ['id' => '111', 'name' => 'Test']);
        assertMatches('/^[0-9a-f]{24}$/', $token);
        $shares = activeShares();
        assertSame('folder/one', $shares[$token]['rel']);
        assertSame('111', $shares[$token]['by']);
        assertTrue($shares[$token]['expires'] > time());

        $forever = createShare('folder/two', 0, ['id' => '111', 'name' => 'Test']);
        assertNull(activeShares()[$forever]['expires'], 'no end');

        // an expired link is dropped
        writeData('shares', [$forever => ['rel' => 'folder/two', 'by' => '111', 'name' => 'T', 'created' => 1, 'expires' => time() - 1]]);
        assertSame([], activeShares());
    });

    test('the background conversion queue', function () {
        $id = addMediaJob('album/a.webm', '111', 'Test', 1000, 'webm', 'src');
        $jobs = mediaJobs();
        assertSame('pending', $jobs[$id]['status']);
        assertSame('a.webm', $jobs[$id]['name']);
        assertSame(1, count(userMediaJobs('111')));
        assertSame(0, count(userMediaJobs('222')), 'only its own');

        $claimed = claimMediaJob();
        assertSame($id, $claimed['id']);
        assertSame('converting', mediaJobs()[$id]['status']);
        assertNull(claimMediaJob(), 'one at a time');

        updateMediaJob($id, ['status' => 'done', 'targetRel' => 'album/a.webm', 'sizeTo' => 500]);
        $job = mediaJobs()[$id];
        assertSame('done', $job['status']);
        assertSame('WebM', mediaJobLabel($job));
        assertContains('gotowe', mediaJobText($job));
        assertFalse(mediaJobActive($job));

        assertSame(1, clearFinishedMediaJobs('111'));
        assertSame([], mediaJobs());
    });

    test('the job labels and the summary', function () {
        assertSame('W kolejce', mediaJobLabel(['kind' => 'webp', 'status' => 'pending']));
        assertSame('Konwersja…', mediaJobLabel(['kind' => 'webp', 'status' => 'converting']));
        assertSame('MP4', mediaJobLabel(['kind' => 'webm', 'status' => 'skipped']));
        assertSame('Błąd', mediaJobLabel(['kind' => 'webp', 'status' => 'failed']));
        assertTrue(mediaJobActive(['status' => 'pending']));
        assertFalse(mediaJobActive(['status' => 'done']));

        $summary = mediaJobsSummary([
            ['status' => 'pending'], ['status' => 'done'],
            ['status' => 'failed'], ['status' => 'failed'],
        ]);
        assertContains('w toku', $summary);
        assertContains('2 błędy', $summary);
    });

    test('pruneMediaJobs resumes a stuck one and drops an old one', function () {
        $now = time();
        writeData('media-jobs', [
            'stuck' => ['rel' => 'a.webm', 'status' => 'converting', 'updated' => $now - MEDIA_JOB_STALE - 1, 'created' => $now, 'by' => '1'],
            'old' => ['rel' => 'b.webm', 'status' => 'done', 'updated' => $now - MEDIA_JOB_KEEP_DAYS * 86400 - 1, 'created' => 1, 'by' => '1'],
            'fresh' => ['rel' => 'c.webm', 'status' => 'done', 'updated' => $now, 'created' => $now, 'by' => '1'],
        ]);
        pruneMediaJobs();
        $jobs = mediaJobs();
        assertSame('pending', $jobs['stuck']['status'], 'a restart resumes it');
        assertFalse(isset($jobs['old']));
        assertTrue(isset($jobs['fresh']));
    });

    test('zipCollect walks a folder and skips a name', function () {
        $base = tempDir();
        mkdir($base . '/sub');
        file_put_contents($base . '/a.png', 'aa');
        file_put_contents($base . '/index.php', 'x');
        file_put_contents($base . '/sub/b.png', 'bb');

        $files = [];
        $bytes = 0;
        zipCollect($base, 'i', ['index.php'], $files, $bytes);
        $names = array_column($files, 1);
        assertContains('i/a.png', $names);
        assertContains('i/sub/b.png', $names);
        assertFalse(in_array('i/index.php', $names, true), 'the skipped name stays out');
        assertSame(4, $bytes);
    });

    test('removeTree, treeSize and treeUse', function () {
        $base = tempDir();
        mkdir($base . '/sub');
        file_put_contents($base . '/a.png', str_repeat('x', 10));
        file_put_contents($base . '/sub/b.png', str_repeat('x', 5));
        assertSame(15, treeSize($base));
        list($count, $bytes) = treeUse($base);
        assertSame(2, $count);
        assertSame(15, $bytes);
        assertTrue(removeTree($base));
        assertFalse(is_dir($base));
    });

    test('searchFolder matches every word in the name', function () {
        $base = tempDir();
        mkdir($base . '/cats');
        file_put_contents($base . '/cat-photo.png', 'x');
        file_put_contents($base . '/dog-photo.png', 'x');
        $found = ['folders' => [], 'files' => [], 'more' => false];
        searchFolder($base, '', ['cat'], $found, 0);
        assertSame(1, count($found['folders']));
        assertSame(1, count($found['files']));
        assertSame('cats', $found['folders'][0]['name']);
        assertSame('cat-photo.png', $found['files'][0]['name']);
    });

    test('allFolders and the path helpers', function () {
        $base = tempDir();
        mkdir($base . '/a/b', 0700, true);
        $folders = allFolders($base);
        assertContains('a', $folders);
        assertContains('a/b', $folders);
        assertSame('i/a/b.png', galleryPath('a/b.png'));
        assertSame('i', galleryPath(''));
        assertSame('i/a/b.png', displayPath('a/b.png'));
        assertSame('2 elementy', countLabel(2));
        assertTrue(is_bool(canZip()));
    });
