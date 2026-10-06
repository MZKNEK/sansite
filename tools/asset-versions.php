<?php
    // Rewrites the "?v=" version of every CSS and JS a page links to the first
    // ten characters of the file's SHA-256, so a change gets a new address and
    // the browser may keep the old one for a year (server/nginx/sanakan.conf).
    // Run it after changing a stylesheet or script, or let
    // tools/install-hooks.sh make git run it before every commit:
    //   php tools/asset-versions.php
    // It prints the files it changed, one path per line, and nothing else on
    // standard output, so the hook can stage exactly those.
    $root = str_replace('\\', '/', dirname(__DIR__));

    // only tracked files, so nothing outside the repository is touched; tools/
    // itself is left alone
    exec('git -C ' . escapeshellarg($root) . ' ls-files', $files, $code);
    if ($code !== 0) {
        fwrite(STDERR, "Nie udało się odczytać listy plików z gita.\n");
        exit(1);
    }

    // an asset link in a page: a path to a .css or .js file with "?v=" on it
    $changed = [];
    foreach ($files as $file) {
        if (!preg_match('/\.(?:php|html)$/', $file) || strpos($file, 'tools/') === 0)
            continue;

        $path = $root . '/' . $file;
        $text = @file_get_contents($path);
        if ($text === false || strpos($text, '?v=') === false)
            continue;

        $dir = dirname($file);
        $new = preg_replace_callback(
            '#((?:\.\./|\./|/)?(?:[\w.-]+/)*[\w.-]+\.(?:css|js))\?v=[^"\'&<>\s]+#',
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
