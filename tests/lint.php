<?php
    // Lints every PHP file of the site (php -l): php tests/lint.php. The security
    // policy keeps scripts in files, so a syntax error in any one of them is a
    // broken page; CI runs this too.
    $root = str_replace('\\', '/', dirname(__DIR__));
    $files = [];
    exec('git -C ' . escapeshellarg($root) . ' ls-files "*.php"', $tracked, $code);
    if ($code === 0 && $tracked) {
        foreach ($tracked as $file)
            $files[] = $file;
    } else {
        // no git (a plain copy): walk the tree instead
        $walk = function ($dir) use (&$walk, &$files) {
            foreach (scandir($dir) ?: [] as $name) {
                if ($name[0] === '.')
                    continue;
                $path = $dir . '/' . $name;
                if (is_dir($path))
                    $walk($path);
                else if (substr($name, -4) === '.php')
                    $files[] = ltrim(substr($path, strlen($GLOBALS['root'] ?? '')), '/');
            }
        };
        $GLOBALS['root'] = $root;
        $walk($root);
    }

    $bad = 0;
    foreach ($files as $file) {
        $output = [];
        $status = 1;
        exec('php -l ' . escapeshellarg($root . '/' . ltrim($file, '/')) . ' 2>&1', $output, $status);
        if ($status !== 0) {
            $bad++;
            fwrite(STDOUT, implode("\n", $output) . "\n");
        }
    }
    if ($bad) {
        fwrite(STDOUT, $bad . " file(s) with a syntax error\n");
        exit(1);
    }
    fwrite(STDOUT, 'PHP syntax OK (' . count($files) . " files)\n");
