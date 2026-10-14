<?php
    // Rewrites the "?v=" version of every CSS and JS a page links to the first
    // ten characters of the file's SHA-256, so a change gets a new address and
    // the browser may keep the old one for a year (server/nginx/sanakan.conf).
    // The service worker, sw.js, gets the same for the files of its offline
    // page (the page itself, its fonts and icon): a change to them changes
    // sw.js, which browsers then install anew, with the new files.
    // Run it after changing a stylesheet or script, or let
    // tools/install-hooks.sh make git run it before every commit:
    //   php tools/asset-versions.php
    // It prints the files it changed, one path per line, and nothing else on
    // standard output, so the hook can stage exactly those.
    require __DIR__ . '/skin-catalog.php';
    $root = str_replace('\\', '/', dirname(__DIR__));

    // Offline must work without fetching styles. Embed the canonical default
    // palette instead of maintaining a second copy of its colours.
    $palette = (string)file_get_contents($root . '/css/skins.css');
    $offlinePath = $root . '/offline.html';
    $offline = (string)file_get_contents($offlinePath);
    $themePattern = '~/\* theme:start \*/.*?/\* theme:end \*/~s';
    if (!preg_match($themePattern, $palette, $theme)) {
        fwrite(STDERR, "Brak domyślnej palety w css/skins.css.\n");
        exit(1);
    }
    $newOffline = preg_replace_callback($themePattern, function () use ($theme) { return $theme[0]; }, $offline, 1, $embedded);
    if ($newOffline === null || $embedded !== 1) {
        fwrite(STDERR, "Brak miejsca na paletę w offline.html.\n");
        exit(1);
    }
    $offlineChanged = $newOffline !== $offline;
    if ($offlineChanged && file_put_contents($offlinePath, $newOffline) === false)
        exit(1);

    // only tracked files, so nothing outside the repository is touched; tools/
    // itself is left alone
    exec('git -C ' . escapeshellarg($root) . ' ls-files', $files, $code);
    if ($code !== 0) {
        fwrite(STDERR, "Nie udało się odczytać listy plików z gita.\n");
        exit(1);
    }

    // an asset link in a page: a path to a .css or .js file with "?v=" on it
    // (or, in sw.js and the offline page, a page, a font or an icon)
    $changed = $offlineChanged ? ['offline.html'] : [];
    foreach ($files as $file) {
        if ((!preg_match('/\.(?:php|html)$/', $file) && $file !== 'sw.js') || strpos($file, 'tools/') === 0)
            continue;

        $path = $root . '/' . $file;
        $text = @file_get_contents($path);
        if ($text === false || strpos($text, '?v=') === false)
            continue;

        $dir = dirname($file);
        $new = preg_replace_callback(
            '#((?:\.\./|\./|/)?(?:[\w.-]+/)*[\w.-]+\.(?:css|js|html|woff2|svg))\?v=[^"\'&<>\s]+#',
            function ($match) use ($root, $dir) {
                $target = $match[1];
                // a leading "/" means the site's root, otherwise the page's folder
                $abs = $target[0] === '/'
                    ? $root . '/' . substr($target, 1)
                    : $root . '/' . ($dir === '.' ? '' : $dir . '/') . $target;
                $real = realpath(str_replace('\\', '/', $abs));
                if ($real === false || !is_file($real))
                    return $match[0];

                return $match[1] . '?v=' . substr(hash_file('sha256', $real), 0, 10);
            },
            $text
        );

        if ($new === null || $new === $text)
            continue;
        if (@file_put_contents($path, $new) === false) {
            fwrite(STDERR, 'Nie udało się zapisać ' . $file . ".\n");
            exit(1);
        }
        $changed[] = $file;
    }

    echo implode("\n", $changed) . ($changed ? "\n" : '');
