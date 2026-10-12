<?php
    // How the code is split into files: every function in one place, the
    // modules of a part loaded by its file in inc/ and only defining things,
    // and none of them taking the site's folder for its own.

    // the PHP files of the site's code, not of the tests or the tools
    function siteSources()
    {
        $root = str_replace('\\', '/', dirname(__DIR__));
        $files = [];
        $walk = function ($dir) use (&$walk, &$files, $root) {
            foreach (scandir($dir) ?: [] as $name) {
                if ($name[0] === '.' || in_array($name, ['tests', 'tools', 'apps', 'node_modules', 'data', 'i', 'server'], true))
                    continue;
                $path = $dir . '/' . $name;
                if (is_dir($path))
                    $walk($path);
                else if (substr($name, -4) === '.php')
                    $files[] = substr($path, strlen($root) + 1);
            }
        };
        $walk($root);
        $files[] = 'i/index.php';

        return $files;
    }

    test('every function is defined in one place', function () {
        $root = dirname(__DIR__);
        $seen = [];
        foreach (siteSources() as $file)
            if (preg_match_all('/^    function &?(\w+)\(/m', (string)file_get_contents($root . '/' . $file), $found))
                foreach ($found[1] as $name)
                    $seen[strtolower($name)][] = $file;
        $twice = array_filter($seen, function ($files) { return count($files) > 1; });
        assertSame([], $twice, 'defined more than once');
    });

    test('the modules of a part only define things and are all loaded', function () {
        $root = str_replace('\\', '/', dirname(__DIR__));
        // each part and the file that loads its modules
        foreach (['gallery' => 'inc/gallery.php', 'auth' => 'inc/auth.php', 'admin' => 'admin/index.php'] as $part => $loader) {
            $facade = (string)file_get_contents($root . '/' . $loader);
            foreach (glob($root . '/inc/' . $part . '/*.php') as $module) {
                $name = basename($module);
                assertTrue(strpos($facade, "/" . $part . '/' . $name . "'") !== false, $name . ' is loaded by ' . $loader);
                $code = (string)file_get_contents($module);
                // at the top level only comments, functions and constants
                foreach (explode("\n", $code) as $line)
                    if (preg_match('/^    (?!\/\/|function |const |[{}\]]|$)\S/', $line))
                        test_assert(false, $name . ' does something when loaded: ' . trim($line));
                // (inside a function it is done when asked, as authConfigured() reads inc/config.php)
                assertFalse((bool)preg_match('/^    (require|include)/m', $code), $name . ' loads nothing itself');
                // one folder deeper than inc/: the site's folder is two up
                assertFalse((bool)preg_match('/dirname\(__DIR__\)/', $code), $name . ' takes inc/ for the site\'s folder');
            }
        }
    });

    // writeData() puts a whole document in place of what is there: right for
    // what one writer makes whole each time, wrong for a list others change
    // too, which goes through updateData() (inc/storage.php). A new use has to
    // be one of the first kind and be added here.
    test('a whole document is written over only where nothing else changes it', function () {
        $allowed = [
            // the state of the automatic blocking, only its run (under autoblock.lock) writes it
            'inc/autoblock.php' => ['autoblock'],
            // what Cloudflare said, made again from its answers
            'inc/cloudflare.php' => ['cloudflare-pause', 'cloudflare-list'],
            // the panel's numbers, counted again whole
            'inc/panel-stats.php' => ['panel-stats'],
            // the mirror of the cards' pictures, only its sync (under pw.lock) writes it
            'inc/pw.php' => ['pw'],
        ];
        $root = dirname(__DIR__);
        $found = [];
        foreach (siteSources() as $file)
            if (preg_match_all("/\bwriteData\('([\w-]+)'/", (string)file_get_contents($root . '/' . $file), $match))
                $found[$file] = array_values(array_unique($match[1]));
        ksort($found);
        assertSame($allowed, $found);
    });
