<?php
    // inc/gallery/import.php: a folder sent by tools/gallery-upload.sh added to
    // the gallery at the same paths, the files already there skipped.

    // a 1x1 PNG, which getimagesize() reads without GD
    function fx_importPng()
    {
        return base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==');
    }

    function fx_importWebm()
    {
        return "\x1A\x45\xDF\xA3" . "\x42\x82\x84webm" . str_repeat("\0", 32);
    }

    test('importTree adds a folder at the same paths, makes missing folders and skips what is there', function () {
        $base = tempDir();
        $source = tempDir();
        mkdir($source . '/Sub', 0700);
        mkdir($source . '/.thumbs', 0700);
        file_put_contents($source . '/a.png', fx_importPng());
        file_put_contents($source . '/b.webm', fx_importWebm());
        file_put_contents($source . '/fake.png', 'not a picture');
        file_put_contents($source . '/notes.txt', 'x');
        file_put_contents($source . '/Sub/c.png', fx_importPng());
        file_put_contents($source . '/.thumbs/t.png', fx_importPng());
        mkdir($base . '/Anime/2026', 0755, true);
        file_put_contents($base . '/Anime/2026/b.webm', 'old');

        $lines = [];
        $counts = importTree($base, $source, 'Anime/2026', false, function ($line) use (&$lines) { $lines[] = $line; });

        assertSame(['added' => 2, 'skipped' => 1, 'failed' => 2], $counts);
        assertTrue(is_file($base . '/Anime/2026/a.png'));
        assertTrue(is_file($base . '/Anime/2026/Sub/c.png'));
        assertSame('old', file_get_contents($base . '/Anime/2026/b.webm'), 'an existing file is left alone');
        assertFalse(file_exists($base . '/Anime/2026/b (2).webm'));
        assertFalse(file_exists($base . '/Anime/2026/fake.png'));
        assertFalse(file_exists($base . '/Anime/2026/.thumbs'));
        assertContains('+ i/Anime/2026/Sub/', implode("\n", $lines));
        $history = implode("\n", array_column(historyEntries(10), 'text'));
        assertContains('Dodano i/Anime/2026/a.png.', $history);
        assertContains('Utworzono folder i/Anime/2026/Sub.', $history);
        assertSame('skrypt', historyEntries(1)[0]['name']);
    });

    test('importFolder never makes the folder of an account', function () {
        $base = tempDir();
        mkdir($base . '/users', 0755);
        $result = importFolder($base, 'users/123-Nobody/x', function () {});
        assertTrue(is_string($result));
        assertFalse(file_exists($base . '/users/123-Nobody'));
        assertTrue(is_string(importFolder($base, 'a/../b', function () {})));
    });

    test('importMissing lists only what the gallery lacks, a PNG changed to WebP counting as there', function () {
        $base = tempDir();
        mkdir($base . '/A/Sub', 0755, true);
        file_put_contents($base . '/A/have.png', 'x');
        file_put_contents($base . '/A/conv.webp', 'x');
        file_put_contents($base . '/A/both.webp', 'x');
        file_put_contents($base . '/A/Sub/clip.webm', 'x');

        $files = ['have.png', 'new.png', 'conv.png', 'both.png', 'both.webp', 'Sub/clip.mp4', 'Sub/new.webm', 'B/x.png'];
        assertSame(['new.png', 'both.png', 'Sub/new.webm', 'B/x.png'], importMissing($base, 'A', $files));
    });

    test('a job queued by the import names no account', function () {
        $id = addMediaJob('A/a.png', '', IMPORT_WHO, 10, 'webp');
        assertTrue($id !== null);
        $job = readData('media-jobs')[$id];
        assertSame('', $job['by']);
        assertSame(IMPORT_WHO, $job['byName']);
    });
