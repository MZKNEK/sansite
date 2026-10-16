<?php
    // Adds a folder of files to the gallery at a path in i/, with its
    // subfolders, skipping the files already there (inc/gallery/import.php).
    // Run by tools/gallery-upload.sh as the web server's user:
    //   ... | php inc/gallery-import.php --missing <path in i/>
    //       of the files listed on stdin (relative paths, each ended by NUL),
    //       prints the ones the gallery does not have yet, the same way
    //   php inc/gallery-import.php [--webp] <folder with the files> <path in i/>
    //       adds them; exits 1 when a file or folder could not be added
    // Only from the command line; inc/ is also blocked from the web in nginx.
    if (PHP_SAPI !== 'cli') {
        http_response_code(404);
        exit;
    }

    require __DIR__ . '/gallery.php';

    $webp = false;
    $missing = false;
    $args = [];
    foreach (array_slice($argv, 1) as $arg) {
        if ($arg === '--webp')
            $webp = true;
        elseif ($arg === '--missing')
            $missing = true;
        elseif (strpos($arg, '--') === 0) {
            fwrite(STDERR, 'Nieznana opcja: ' . $arg . "\n");
            exit(2);
        } else
            $args[] = $arg;
    }
    if ($missing ? count($args) !== 1 : count($args) !== 2 || !is_dir($args[0])) {
        fwrite(STDERR, "Użycie: php inc/gallery-import.php --missing <ścieżka w i/> < lista\n"
            . "       php inc/gallery-import.php [--webp] <folder z plikami> <ścieżka w i/>\n");
        exit(2);
    }

    $base = str_replace('\\', '/', realpath(__DIR__ . '/../i'));

    if ($missing) {
        $files = [];
        foreach (explode("\0", (string)stream_get_contents(STDIN)) as $file)
            if (($file = preg_replace('~^(\./)+~', '', str_replace('\\', '/', $file))) !== '')
                $files[] = $file;
        foreach (importMissing($base, trim($args[0], '/'), $files) as $file)
            fwrite(STDOUT, $file . "\0");
        exit(0);
    }

    $counts = importTree($base, $args[0], trim($args[1], '/'), $webp, function ($line) { fwrite(STDOUT, $line . "\n"); });
    fwrite(STDOUT, 'Dodano ' . $counts['added'] . ', pominięto (już były) ' . $counts['skipped'] . ', błędy ' . $counts['failed'] . ".\n");
    exit($counts['failed'] ? 1 : 0);
